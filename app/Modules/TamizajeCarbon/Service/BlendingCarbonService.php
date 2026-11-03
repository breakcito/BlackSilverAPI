<?php

namespace App\Modules\TamizajeCarbon\Service;

use App\Modules\TamizajeCarbon\Data\BlendingCarbonData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;

/**
 * Reglas de negocio del blending de carbon.
 *
 * Un blending mezcla varias calidades del mismo tipo de carbon: toma stock de
 * cada una (sin bloquear: si el usuario pide mas de lo que hay, el stock
 * queda en 0) y produce un lote con su propio stock.
 */
class BlendingCarbonService
{
    /**
     * Calidades que se pueden tomar en un blending.
     *
     * El catalogo `calidad_carbon` tiene una cuarta calidad ("Calidad", para
     * el carbon con mas de 30% de ceniza) que NO entra al blending: es
     * material que ya no se procesa.
     */
    public const CALIDADES_BLENDING = ['Baja', 'Intermedia', 'Alta'];

    /**
     * Porcentaje de ceniza a partir del cual se avisa (sin bloquear) que el
     * blending sale del rango comercial habitual.
     */
    public const AVISO_PORCENTAJE_CENIZA = 15.0;

    /**
     * Supervisor de planta del blending.
     *
     * El reporte de planta lo firma siempre el mismo empleado, igual que en
     * el zarandeo: el sistema lo elige solo y el usuario no lo cambia.
     */
    public const ID_EMPLEADO_SUPERVISOR = 8;

    /**
     * Carpeta donde se guardan las evidencias de los blendings.
     */
    public const CARPETA_EVIDENCIAS = 'evidencias-blending-carbon';

    /**
     * Sube y acumula nuevas evidencias a un blending existente.
     *
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     */
    public static function subir_evidencias(int $id_blending, array $archivos): array
    {
        $blending = BlendingCarbonData::get_blending_by_id($id_blending);

        if ($blending === null) {
            return ApiResponse::error('El blending indicado no existe');
        }

        $existentes = is_array($blending->evidencias ?? null) ? $blending->evidencias : [];
        $nuevas = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS, $archivos);
        $todas = array_merge($existentes, $nuevas);

        BlendingCarbonData::actualizar_evidencias($id_blending, $todas);

        return ApiResponse::success($todas, 'Evidencias agregadas correctamente');
    }

    /**
     * Elimina una evidencia de un blending: borra el archivo fisico del disco
     * y la quita del registro JSON.
     */
    public static function eliminar_evidencia(int $id_blending, string $path_relativo): array
    {
        $blending = BlendingCarbonData::get_blending_by_id($id_blending);

        if ($blending === null) {
            return ApiResponse::error('El blending indicado no existe');
        }

        $existentes = is_array($blending->evidencias ?? null) ? $blending->evidencias : [];

        ArchivoHelper::eliminarArchivo($path_relativo);

        $actualizadas = array_values(array_filter(
            $existentes,
            fn ($e) => ($e['path_relativo'] ?? '') !== $path_relativo
        ));

        BlendingCarbonData::actualizar_evidencias($id_blending, $actualizadas);

        return ApiResponse::success($actualizadas, 'Evidencia eliminada correctamente');
    }

    /**
     * Lista los blendings.
     *
     * @param array<string, mixed> $opts
     */
    public static function get_blendings(array $opts = []): array
    {
        return ApiResponse::success(BlendingCarbonData::get_blendings($opts));
    }

    /**
     * Obtiene un blending con sus detalles.
     */
    public static function get_blending(int $id_blending): array
    {
        $blending = BlendingCarbonData::get_blending_by_id($id_blending);

        if ($blending === null) {
            return ApiResponse::error('El blending indicado no existe');
        }

        return ApiResponse::success($blending);
    }

    /**
     * Registra un blending y descuenta del stock el carbon que toma.
     *
     * @param array<string, mixed> $payload
     */
    public static function registrar_blending(array $payload, int $id_empleado): array
    {
        $error = self::validar_cabecera($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $idAlmacen = (int) ($payload['id_almacen'] ?? 0);
        if ($idAlmacen <= 0) {
            $idAlmacen = self::almacen_por_defecto();
            if ($idAlmacen <= 0) {
                return ApiResponse::error('No hay almacenes de carbon registrados en el sistema');
            }
        }

        $idTipoCarbon = (int) $payload['id_tipo_carbon'];
        $detalles = self::validar_detalles($payload['detalles'] ?? [], $idAlmacen, $idTipoCarbon);
        if ($detalles === null) {
            return ApiResponse::error(self::$ultimoErrorDetalle);
        }

        try {
            $idBlending = BlendingCarbonData::insert_blending([
                'id_almacen' => $idAlmacen,
                'id_empleado_registro' => $id_empleado,
                'id_empleado_supervisor' => self::supervisor_de($payload),
                'id_tipo_carbon' => $idTipoCarbon,
                'porcentaje_ceniza' => (float) $payload['porcentaje_ceniza'],
                'fecha_hora_blending' => (string) ($payload['fecha_hora_blending'] ?? now()->toDateTimeString()),
                'evidencias' => $payload['evidencias'] ?? null,
                'detalles' => $detalles,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $blending = BlendingCarbonData::get_blending_by_id($idBlending);

        return ApiResponse::success(
            $blending ?? ['id_blending_carbon' => $idBlending],
            'Blending registrado correctamente'
        );
    }

    /**
     * Actualiza un blending: recalcula el stock tomado y sus importes.
     *
     * @param array<string, mixed> $payload
     */
    public static function actualizar_blending(int $id_blending, array $payload): array
    {
        if (!BlendingCarbonData::existe_blending($id_blending)) {
            return ApiResponse::error('El blending indicado no existe');
        }

        $error = self::validar_cabecera($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $blending = BlendingCarbonData::get_blending($id_blending);
        $idAlmacen = (int) ($blending->id_almacen ?? 0);
        $idTipoCarbon = (int) $payload['id_tipo_carbon'];

        $detalles = self::validar_detalles($payload['detalles'] ?? [], $idAlmacen, $idTipoCarbon);
        if ($detalles === null) {
            return ApiResponse::error(self::$ultimoErrorDetalle);
        }

        // Las evidencias solo se tocan si se subieron archivos nuevos: si
        // no, se conservan las que ya tenia el blending.
        $cambios = [
            'id_blending_carbon' => $id_blending,
            'id_empleado_supervisor' => self::supervisor_de($payload),
            'id_tipo_carbon' => $idTipoCarbon,
            'porcentaje_ceniza' => (float) $payload['porcentaje_ceniza'],
            'fecha_hora_blending' => (string) ($payload['fecha_hora_blending'] ?? now()->toDateTimeString()),
            'detalles' => $detalles,
        ];

        if (isset($payload['evidencias'])) {
            $cambios['evidencias'] = $payload['evidencias'];
        }

        try {
            BlendingCarbonData::update_blending($cambios);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $actualizado = BlendingCarbonData::get_blending_by_id($id_blending);

        return ApiResponse::success($actualizado, 'Blending actualizado correctamente');
    }

    /**
     * Agrega mas stock tomado a un blending ya registrado.
     *
     * @param array<string, mixed> $payload
     */
    public static function agregar_detalles(int $id_blending, array $payload): array
    {
        if (!BlendingCarbonData::existe_blending($id_blending)) {
            return ApiResponse::error('El blending indicado no existe');
        }

        $blending = BlendingCarbonData::get_blending($id_blending);
        $idAlmacen = (int) ($blending->id_almacen ?? 0);
        $idTipoCarbon = (int) ($blending->id_tipo_carbon ?? 0);

        $detalles = self::validar_detalles($payload['detalles'] ?? [], $idAlmacen, $idTipoCarbon);
        if ($detalles === null) {
            return ApiResponse::error(self::$ultimoErrorDetalle);
        }

        try {
            BlendingCarbonData::agregar_detalles($id_blending, $detalles);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $actualizado = BlendingCarbonData::get_blending_by_id($id_blending);

        return ApiResponse::success($actualizado, 'Stock agregado al blending');
    }

    /**
     * Supervisor del blending: el que viene en el payload o, por defecto, el
     * supervisor de planta.
     */
    private static function supervisor_de(array $payload): int
    {
        $id = (int) ($payload['id_empleado_supervisor'] ?? 0);

        return $id > 0 ? $id : self::ID_EMPLEADO_SUPERVISOR;
    }

    /**
     * Ultimo error de la validacion de detalles (para no repetir el mensaje
     * en dos lugares).
     */
    private static ?string $ultimoErrorDetalle = null;

    /**
     * Valida la cabecera del blending.
     *
     * @param array<string, mixed> $payload
     */
    private static function validar_cabecera(array $payload): ?string
    {
        // El supervisor lo elige el sistema (el mismo de planta que firma el
        // zarandeo). Si no viene, se aplica ese.
        $idSupervisor = (int) ($payload['id_empleado_supervisor'] ?? 0);
        if ($idSupervisor <= 0) {
            $idSupervisor = self::ID_EMPLEADO_SUPERVISOR;
        }

        $existeSupervisor = BlendingCarbonData::existe_empleado($idSupervisor);
        if (!$existeSupervisor) {
            return 'El supervisor del blending no existe';
        }

        $idTipoCarbon = (int) ($payload['id_tipo_carbon'] ?? 0);
        if ($idTipoCarbon <= 0) {
            return 'Debe indicar el tipo de carbon del blending';
        }

        $existeTipo = BlendingCarbonData::existe_tipo_carbon($idTipoCarbon);
        if (!$existeTipo) {
            return 'El tipo de carbon seleccionado no existe';
        }

        $porcentajeCeniza = (float) ($payload['porcentaje_ceniza'] ?? 0);
        if ($porcentajeCeniza < 0 || $porcentajeCeniza > 100) {
            return 'El porcentaje de ceniza debe estar entre 0 y 100';
        }

        return null;
    }

    /**
     * Valida los detalles (stock tomado) del blending.
     *
     * Cada fila debe apuntar a una fila de `stock_carbon` del mismo almacen y
     * del mismo tipo de carbon del blending, y de una calidad que se pueda
     * mezclar.
     *
     * @param array<int, array<string, mixed>> $detalles
     * @return array<int, array{id_stock_carbon: int, cantidad_tomada: float}>|null
     */
    private static function validar_detalles(array $detalles, int $idAlmacen, int $idTipoCarbon): ?array
    {
        self::$ultimoErrorDetalle = null;

        if (!BlendingCarbonData::detalle_tiene_blending()) {
            self::$ultimoErrorDetalle = 'Falta la columna detalle_blending_carbon.id_blending_carbon: '
                . 'aplica el script SQL del modulo de produccion de carbon';
            return null;
        }

        if (empty($detalles) || !is_array($detalles)) {
            self::$ultimoErrorDetalle = 'Debe indicar al menos una calidad con cantidad a tomar';
            return null;
        }

        $limpios = [];
        $vistos = [];

        foreach ($detalles as $i => $detalle) {
            $n = $i + 1;

            $idStock = (int) ($detalle['id_stock_carbon'] ?? 0);
            if ($idStock <= 0) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: debe indicar el stock a tomar";
                return null;
            }

            if (isset($vistos[$idStock])) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: la calidad esta repetida";
                return null;
            }
            $vistos[$idStock] = true;

            $cantidad = (float) ($detalle['cantidad_tomada'] ?? 0);
            if ($cantidad < 0) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: la cantidad no puede ser negativa";
                return null;
            }

            $stock = BlendingCarbonData::get_stock_carbon($idStock);
            if ($stock === null) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: el stock indicado no existe";
                return null;
            }

            if ((int) $stock->id_almacen !== $idAlmacen) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: el stock pertenece a otro almacen";
                return null;
            }

            if ((int) $stock->id_tipo_carbon !== $idTipoCarbon) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: el stock pertenece a otro tipo de carbon";
                return null;
            }

            $calidad = (string) ($stock->calidad ?? '');
            if (!in_array($calidad, self::CALIDADES_BLENDING, true)) {
                self::$ultimoErrorDetalle = "Detalle #{$n}: la calidad {$calidad} no se puede mezclar";
                return null;
            }

            $limpios[] = [
                'id_stock_carbon' => $idStock,
                'cantidad_tomada' => round($cantidad, 4),
            ];
        }

        $conCantidad = array_values(array_filter(
            $limpios,
            fn ($d) => $d['cantidad_tomada'] > 0
        ));

        if (empty($conCantidad)) {
            self::$ultimoErrorDetalle = 'Debe indicar al menos una calidad con cantidad a tomar';
            return null;
        }

        return $conCantidad;
    }

    /**
     * Almacen de carbon por defecto: el primero habilitado para carbon.
     */
    public static function almacen_por_defecto(): int
    {
        return BlendingCarbonData::almacen_carbon_por_defecto();
    }
}
