<?php

namespace App\Modules\ActivosFijos\Service;

use App\Modules\ActivosFijos\Data\ActivosData;
use App\Services\ActivosFijosService as GlobalActivosService;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ActivosService
{
    /**
     * Listar todos los activos fijos con su información detallada.
     */
    public static function get_activos()
    {
        $activos = ActivosData::get_activos();
        return ApiResponse::success($activos);
    }

    /**
     * Crear un nuevo activo fijo consumiendo el servicio global.
     */
    public static function crear_activo(
        int $id_producto,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_marca = null,
        //
        ?string $codigo = null,
        ?string $numero_serie = null,
        ?string $modelo = null,
        ?int $yearcito_modelo = null,
        ?string $descripcion = null,
        ?string $serie_placa = null,
        ?string $numero_placa = null,
        ?array $especificaciones = null,
        ?string $fecha_hora_ingreso = null,
        ?EstadoActivoFijo $estado = EstadoActivoFijo::EnUso,
        // Nuevos
        ?int $id_empleado_responsable = null,
        ?string $serie_factura_compra = null,
        ?string $numero_factura_compra = null,
        ?float $costo_compra = null,
        ?int $id_labor = null,
        ?array $ids_labores_abastecidas = null,
        ?array $evidencias = null
    ) {
        $res = GlobalActivosService::crear_activo(
            id_producto: $id_producto,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            id_marca: $id_marca,
            codigo: $codigo,
            numero_serie: $numero_serie,
            modelo: $modelo,
            yearcito_modelo: $yearcito_modelo,
            descripcion: $descripcion,
            serie_placa: $serie_placa,
            numero_placa: $numero_placa,
            especificaciones: $especificaciones,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            estado: $estado,
            return_objecto: false,
            // Nuevos
            id_empleado_responsable: $id_empleado_responsable,
            serie_factura_compra: $serie_factura_compra,
            numero_factura_compra: $numero_factura_compra,
            costo_compra: $costo_compra,
            id_labor: $id_labor,
            ids_labores_abastecidas: $ids_labores_abastecidas,
            evidencias: $evidencias
        );

        $id_activo = $res['data'];

        return ApiResponse::success(ActivosData::get_activos((int) $id_activo));
    }

    /**
     * Actualizar la ubicación de un activo fijo consumiendo el servicio global.
     */
    public static function actualizar_ubicacion(
        int $id_activo,
        MovimientoActivoFijo $tipo_movimiento,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion = null,
        ?string $fecha_hora_movimiento = null
    ) {
        $id_log = GlobalActivosService::new_ubicacion(
            id_activo: $id_activo,
            tipo_movimiento: $tipo_movimiento,
            id_almacen: $id_almacen,
            id_mina: $id_mina,
            descripcion: $descripcion,
            fecha_hora_movimiento: $fecha_hora_movimiento
        );

        return ApiResponse::success($id_log, 'Ubicación actualizada correctamente');
    }

    /**
     * Editar un activo fijo existente.
     * - Actualiza metadata (codigo, modelo, serie, placa, estado, especificaciones, etc.) + labor.
     * - Si cambió id_almacen o id_mina respecto al estado actual,
     *   registra un movimiento en activo_fijo_ubicacion_log con el
     *   MovimientoActivoFijo derivado de la transición.
     *
     * @param array $data Campos editables crudos del Request. El Service normaliza
     *                    el formato de `especificaciones` (json_encode si es array)
     *                    y valida que `estado`, si viene, sea un EstadoActivoFijo válido.
     *                    Para `estado` se respeta el valor enviado por el usuario
     *                    cuando NO hay cambio de ubicación. Si hay cambio de ubicación
     *                    Y el usuario envió `estado`, el `estado` enviado gana sobre
     *                    el cálculo automático de new_ubicacion.
     */
    public static function actualizar_activo(
        int $id_activo,
        array $data,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?string $descripcion_ubicacion = null,
        ?string $fecha_hora_movimiento = null
    ) {
        // Obtener ubicación actual para detectar cambios
        $actual = DB::table('activo_fijo')
            ->where('id', $id_activo)
            ->select(['id_almacen', 'id_mina'])
            ->first();

        if (!$actual) {
            return ApiResponse::error('El activo que intenta editar no existe.');
        }

        $id_almacen_anterior = $actual->id_almacen !== null ? (int) $actual->id_almacen : null;
        $id_mina_anterior = $actual->id_mina !== null ? (int) $actual->id_mina : null;

        $hubo_cambio_ubicacion =
            $id_almacen_anterior !== $id_almacen
            || $id_mina_anterior !== $id_mina;

        // Normalizar `especificaciones` (json_encode si viene como array).
        // Array vacío o null → null en BD.
        if (array_key_exists('especificaciones', $data)) {
            $esp = $data['especificaciones'];
            if ($esp === null) {
                $data['especificaciones'] = null;
            } elseif (is_array($esp)) {
                $data['especificaciones'] = empty($esp) ? null : json_encode(array_values($esp));
            }
        }

        // `estado` puede venir como string (valor del enum) o como array.
        // Lo guardamos como string para que el cast del modelo lo maneje.
        // Solo se incluye si el usuario lo envió explícitamente.
        $estado_enviado_por_usuario = null;
        if (array_key_exists('estado', $data)) {
            $estadoValor = $data['estado'];
            if ($estadoValor !== null && $estadoValor !== '') {
                // Validar que sea un EstadoActivoFijo válido
                $estadoEnum = EstadoActivoFijo::tryFrom((string) $estadoValor);
                if ($estadoEnum === null) {
                    return ApiResponse::error('Estado de activo inválido.');
                }
                $data['estado'] = $estadoEnum->value;
                $estado_enviado_por_usuario = $estadoEnum->value;
            } else {
                $data['estado'] = null;
            }
        }

        return DB::transaction(function () use ($id_activo, $data, $id_almacen, $id_mina, $id_almacen_anterior, $id_mina_anterior, $hubo_cambio_ubicacion, $descripcion_ubicacion, $fecha_hora_movimiento, $estado_enviado_por_usuario) {
            // 1) Actualizar metadata (incluye id_labor, estado, especificaciones si vienen)
            ActivosData::actualizar_activo($id_activo, $data);

            // 2) Si cambió ubicación física (almacén/mina), registrar log
            if ($hubo_cambio_ubicacion) {
                $tipo_movimiento = self::derivar_movimiento(
                    id_almacen_anterior: $id_almacen_anterior,
                    id_mina_anterior: $id_mina_anterior,
                    id_almacen_nuevo: $id_almacen,
                    id_mina_nuevo: $id_mina
                );

                if ($tipo_movimiento !== null) {
                    GlobalActivosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: $tipo_movimiento,
                        id_almacen: $id_almacen,
                        id_mina: $id_mina,
                        descripcion: $descripcion_ubicacion ?? 'Edición de activo',
                        fecha_hora_movimiento: $fecha_hora_movimiento
                    );
                }
            }

            // 3) Si el usuario envió `estado` y new_ubicacion lo sobrescribió
            //    (porque new_ubicacion recalcula el estado al mover), re-aplicar
            //    el estado del usuario para que tenga prioridad sobre el cálculo
            //    automático.
            if ($hubo_cambio_ubicacion && $estado_enviado_por_usuario !== null) {
                ActivosData::actualizar_activo($id_activo, [
                    'estado' => $estado_enviado_por_usuario,
                ]);
            }

            return ApiResponse::success(
                ActivosData::get_activos($id_activo),
                'Activo actualizado correctamente'
            );
        });
    }

    /**
     * Deriva el MovimientoActivoFijo comparando ubicación anterior vs nueva.
     * Devuelve null si la transición no aplica para log (ej. misma ubicación).
     */
    private static function derivar_movimiento(
        ?int $id_almacen_anterior,
        ?int $id_mina_anterior,
        ?int $id_almacen_nuevo,
        ?int $id_mina_nuevo
    ): ?MovimientoActivoFijo {
        $estaba_en_almacen = $id_almacen_anterior !== null;
        $estaba_en_mina = $id_mina_anterior !== null;
        $va_a_almacen = $id_almacen_nuevo !== null;
        $va_a_mina = $id_mina_nuevo !== null;

        // Ninguno de los dos tenía ubicación previa y no hay nueva → sin movimiento
        if (!$estaba_en_almacen && !$estaba_en_mina && !$va_a_almacen && !$va_a_mina) {
            return null;
        }

        // Misma ubicación exacta → sin movimiento
        if ($estaba_en_almacen === $va_a_almacen && $estaba_en_mina === $va_a_mina) {
            // Pero si solo cambió el id (de un almacén a otro), sí es DeAlmacenAAlmacen
            if ($estaba_en_almacen && $va_a_almacen && $id_almacen_anterior !== $id_almacen_nuevo) {
                return MovimientoActivoFijo::DeAlmacenAAlmacen;
            }
            if ($estaba_en_mina && $va_a_mina && $id_mina_anterior !== $id_mina_nuevo) {
                return MovimientoActivoFijo::DeMinaAMina;
            }
            return null;
        }

        if ($estaba_en_almacen && $va_a_mina)
            return MovimientoActivoFijo::DeAlmacenAMina;
        if ($estaba_en_mina && $va_a_almacen)
            return MovimientoActivoFijo::DeMinaAAlmacen;

        return null;
    }
}
