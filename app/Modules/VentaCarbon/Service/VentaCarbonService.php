<?php

namespace App\Modules\VentaCarbon\Service;

use App\Modules\VentaCarbon\Data\VentaCarbonData;
use App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class VentaCarbonService
{
    private const CARPETA_EVIDENCIAS_VENTA = 'evidencias-venta-carbon';

    /**
     * Lista las ventas de carbon con el arbol completo de cargas,
     * liquidaciones, comprobantes y pagos.
     *
     * @param array<string, mixed> $opts
     */
    public static function get_ventas(array $opts = []): array
    {
        return ApiResponse::success(VentaCarbonData::get_ventas($opts));
    }

    /**
     * Obtiene una venta con el arbol completo de cargas y su liquidacion.
     */
    public static function get_venta(int $id_venta_carbon): array
    {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        return ApiResponse::success($venta);
    }

    /**
     * Registra una venta de carbon con sus cargas opcionales.
     *
     * @param array<string, mixed> $payload
     * @param array<string, array<int, UploadedFile>> $archivosPorCarga
     */
    public static function registrar_venta(array $payload, int $id_empleado, array $archivosPorCarga = []): array
    {
        $error = self::validar_cabecera($payload);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $cargas = $payload['cargas'] ?? [];
        if (!is_array($cargas)) {
            $cargas = [];
        }

        $errorCargas = self::validar_cargas($cargas);
        if ($errorCargas !== null) {
            return ApiResponse::error($errorCargas);
        }

        $errorStock = self::validar_stock_cargas((int) $payload['id_almacen_empresa_salida'], $cargas);
        if ($errorStock !== null) {
            return ApiResponse::error($errorStock);
        }

        $cargas = self::adjuntar_evidencias($cargas, $archivosPorCarga);

        try {
            $resultado = VentaCarbonData::registrar_venta($payload, $cargas, $id_empleado);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $venta = VentaCarbonData::get_venta_por_id($resultado);

        return ApiResponse::success($venta, 'Venta de carbon registrada correctamente');
    }

    /**
     * Añade cargas (despachos) a una venta ya registrada.
     *
     * @param array<int, array<string, mixed>> $cargas
     * @param array<string, array<int, UploadedFile>> $archivosPorCarga
     */
    public static function registrar_cargas(
        int $id_venta_carbon,
        array $cargas,
        int $id_empleado,
        array $archivosPorCarga = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        if (empty($cargas)) {
            return ApiResponse::error('Debe registrar al menos una carga');
        }

        $error = self::validar_cargas($cargas);
        if ($error !== null) {
            return ApiResponse::error($error);
        }

        $errorStock = self::validar_stock_cargas((int) $venta->id_almacen_empresa_salida, $cargas);
        if ($errorStock !== null) {
            return ApiResponse::error($errorStock);
        }

        $cargas = self::adjuntar_evidencias($cargas, $archivosPorCarga);

        try {
            $idsCargas = VentaCarbonData::insert_cargas($id_venta_carbon, $cargas, $id_empleado);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);

        return ApiResponse::success([
            'ids_cargas' => $idsCargas,
            'venta' => $venta,
        ], 'Cargas despachadas correctamente');
    }

    /**
     * Confirma la llegada de una carga al almacen del cliente.
     *
     * @param array<string, mixed> $payload
     * @param array<int, UploadedFile> $archivos
     */
    public static function confirmar_llegada(
        int $id_venta_carbon,
        int $id_carga_venta_carbon,
        array $payload,
        array $archivos = []
    ): array {
        $venta = VentaCarbonData::get_venta_por_id($id_venta_carbon);
        if ($venta === null) {
            return ApiResponse::error('Venta de carbon no encontrada');
        }

        $carga = null;
        foreach ($venta->cargas as $item) {
            if ((int) $item->id_carga_venta_carbon === $id_carga_venta_carbon) {
                $carga = $item;
                break;
            }
        }

        if ($carga === null) {
            return ApiResponse::error('La carga no pertenece a la venta indicada');
        }

        if ($carga->estado !== EstadoCargaVentaCarbon::EnCamino->value) {
            return ApiResponse::error('La llegada de esta carga ya fue confirmada');
        }

        // El tipo de carbon lo hereda el blending de la carga: no se puede
        // corregir al confirmar la llegada.
        $idBlending = (int) ($carga->id_blending_carbon ?? 0);
        if ($idBlending <= 0) {
            return ApiResponse::error('La carga no tiene un blending asignado');
        }

        $blending = VentaCarbonData::get_blending($idBlending);
        if ($blending === null) {
            return ApiResponse::error('El blending de la carga no existe');
        }

        $tipoCarbon = VentaCarbonData::get_tipo_carbon((int) $blending->id_tipo_carbon);
        if ($tipoCarbon === null) {
            return ApiResponse::error('El tipo de carbon del blending no existe');
        }

        // El vehiculo es opcional: se pudo elegir al despachar. Si llega se
        // valida que exista.
        $idVehiculo = (int) ($payload['id_vehiculo_carbon'] ?? 0);
        if ($idVehiculo > 0 && VentaCarbonData::get_vehiculo_carbon($idVehiculo) === null) {
            return ApiResponse::error('El vehiculo seleccionado no existe');
        }

        // La segunda placa SIEMPRE es opcional (tambien para las tolvas).
        $segundaPlaca = trim((string) ($payload['segunda_placa'] ?? ''));

        if (empty($archivos)) {
            $evidencias = $payload['evidencias'] ?? null;
        } else {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_VENTA, $archivos);
        }

        try {
            VentaCarbonData::confirmar_llegada($id_venta_carbon, $id_carga_venta_carbon, [
                'nombre_tipo_carbon' => (string) $tipoCarbon->nombre,
                'cantidad_toneladas' => (float) $payload['cantidad_toneladas'],
                'fecha_hora_ingreso' => (string) $payload['fecha_hora_ingreso'],
                'id_vehiculo_carbon' => $idVehiculo > 0 ? $idVehiculo : null,
                'segunda_placa' => $segundaPlaca,
                'guia_remitente' => $payload['guia_remitente'] ?? null,
                'guia_transportista' => $payload['guia_transportista'] ?? null,
                'codigo_ticket_balanza' => $payload['codigo_ticket_balanza'] ?? null,
                'observacion' => $payload['observacion'] ?? null,
                'porcentaje_ceniza' => $payload['porcentaje_ceniza'] ?? 0,
                'porcentaje_humedad' => $payload['porcentaje_humedad'] ?? 0,
                'porcentaje_azufre' => $payload['porcentaje_azufre'] ?? 0,
                'porcentaje_pizarra' => $payload['porcentaje_pizarra'] ?? 0,
                'analisis_granulometria' => $payload['analisis_granulometria'] ?? null,
                'fue_aceptado' => $payload['fue_aceptado'] ?? false,
                'evidencias' => $evidencias,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage());
        }

        $ventaActualizada = VentaCarbonData::get_venta_por_id($id_venta_carbon);

        return ApiResponse::success($ventaActualizada, 'Llegada confirmada correctamente');
    }

    /**
     * Blendings despachables desde un almacen de salida.
     *
     * Cada uno trae su stock ya descontando lo que salio y sigue en camino.
     */
    public static function get_blendings(int $id_almacen): array
    {
        if ($id_almacen <= 0) {
            return ApiResponse::error('Debe indicar el almacen de salida');
        }

        return ApiResponse::success(
            VentaCarbonData::get_blendings_despachables($id_almacen)
        );
    }

    /**
     * Valida las referencias de la cabecera de la venta.
     * @param array<string, mixed> $payload
     */
    private static function validar_cabecera(array $payload): ?string
    {
        $empresa = VentaCarbonData::get_empresa((int) $payload['id_empresa']);
        if ($empresa === null) {
            return 'La empresa seleccionada no existe';
        }

        $cliente = VentaCarbonData::get_cliente((int) $payload['id_cliente']);
        if ($cliente === null) {
            return 'El cliente seleccionado no existe';
        }
        if ((int) $cliente->para_carbon !== 1) {
            return 'El cliente debe estar habilitado para carbon';
        }

        $clienteDestino = VentaCarbonData::get_cliente((int) $payload['id_cliente_destino']);
        if ($clienteDestino === null) {
            return 'El cliente de destino seleccionado no existe';
        }
        if ((int) $clienteDestino->para_carbon !== 1) {
            return 'El cliente de destino debe estar habilitado para carbon';
        }

        $almacen = VentaCarbonData::get_almacen((int) $payload['id_almacen_empresa_salida']);
        if ($almacen === null) {
            return 'El almacen de salida no existe';
        }
        if ((int) $almacen->para_carbon !== 1) {
            return 'El almacen de salida debe estar habilitado para carbon';
        }

        $almacenCliente = VentaCarbonData::get_almacen_carbon_cliente(
            (int) $payload['id_almacen_cliente_llegada']
        );
        if ($almacenCliente === null) {
            return 'El almacen de llegada no existe';
        }
        if (($almacenCliente->estado ?? 'Activo') !== 'Activo') {
            return 'El almacen de llegada esta inactivo';
        }

        // El almacen de destino debe pertenecer al cliente destino o al
        // cliente facturado: son las dos entidades que pueden recibir la carga.
        $idsClientesPermitidos = [
            (int) $payload['id_cliente_destino'],
            (int) $payload['id_cliente'],
        ];
        if (!in_array((int) $almacenCliente->id_cliente, $idsClientesPermitidos, true)) {
            return 'El almacen de llegada no pertenece al cliente de destino';
        }

        $porcentajeIgv = (float) ($payload['porcentaje_igv'] ?? 0);
        if ($porcentajeIgv < 0 || $porcentajeIgv > 100) {
            return 'El porcentaje de IGV debe estar entre 0 y 100';
        }

        $porcentajeDetraccion = (float) ($payload['porcentaje_detraccion'] ?? 0);
        if ($porcentajeDetraccion < 0 || $porcentajeDetraccion > 100) {
            return 'El porcentaje de detraccion debe estar entre 0 y 100';
        }

        return null;
    }

    /**
     * Valida el arreglo de cargas a despachar.
     *
     * Cada carga apunta a un BLENDING (el lote de carbon que sale del
     * almacen). El vehiculo y la segunda placa son opcionales y se registran
     * en el despacho: ya no hace falta esperarlos para confirmar la llegada.
     *
     * @param array<int, array<string, mixed>> $cargas
     */
    private static function validar_cargas(array $cargas): ?string
    {
        foreach ($cargas as $i => $carga) {
            $n = $i + 1;

            $idBlending = (int) ($carga['id_blending_carbon'] ?? 0);
            if ($idBlending <= 0) {
                return "Carga #{$n}: debe seleccionar el blending a despachar";
            }

            $blending = VentaCarbonData::get_blending($idBlending);
            if ($blending === null) {
                return "Carga #{$n}: el blending seleccionado no existe";
            }

            $cantidad = (float) ($carga['cantidad_toneladas'] ?? 0);
            if ($cantidad <= 0) {
                return "Carga #{$n}: la cantidad de toneladas debe ser mayor a 0";
            }

            if (empty($carga['fecha_hora_salida'])) {
                return "Carga #{$n}: debe indicar la fecha y hora de salida";
            }

            $idVehiculo = (int) ($carga['id_vehiculo_carbon'] ?? 0);
            if ($idVehiculo > 0 && VentaCarbonData::get_vehiculo_carbon($idVehiculo) === null) {
                return "Carga #{$n}: el vehiculo seleccionado no existe";
            }

            $segundaPlaca = trim((string) ($carga['segunda_placa'] ?? ''));
            if ($segundaPlaca !== '' && mb_strlen($segundaPlaca) > 20) {
                return "Carga #{$n}: la segunda placa es demasiado larga";
            }
        }

        return null;
    }

    /**
     * Verifica que el almacen de salida tenga stock suficiente para las cargas.
     *
     * El stock no se descuenta al despachar (el asiento se escribe al
     * confirmar la llegada), asi que se valida contra el stock del blending
     * menos las cargas que ya salieron y siguen pendientes de confirmar.
     *
     * @param array<int, array<string, mixed>> $cargas
     */
    private static function validar_stock_cargas(int $id_almacen_salida, array $cargas): ?string
    {
        if ($id_almacen_salida <= 0) {
            return null;
        }

        $reservado = [];

        foreach ($cargas as $i => $carga) {
            $n = $i + 1;
            $idBlending = (int) ($carga['id_blending_carbon'] ?? 0);
            $cantidad = (float) ($carga['cantidad_toneladas'] ?? 0);

            $blending = VentaCarbonData::get_blending($idBlending);

            // El blending se despacha desde SU almacen: si el lote esta en
            // otro almacen no se puede sacar de este.
            if ($blending !== null && (int) $blending->id_almacen !== $id_almacen_salida) {
                return "Carga #{$n}: el blending {$blending->correlativo} pertenece a otro almacen";
            }

            $disponible = VentaCarbonData::calcular_stock_disponible_blending(
                $idBlending,
                $id_almacen_salida
            );
            $disponible -= (float) ($reservado[$idBlending] ?? 0);

            if ($disponible + 0.0001 < $cantidad) {
                return "Carga #{$n}: no hay stock suficiente del blending "
                    . ($blending->correlativo ?? $idBlending) . ' en el almacen de salida '
                    . '(disponible: '
                    . rtrim(rtrim(number_format($disponible, 2), '0'), '.') . ' TN)';
            }

            $reservado[$idBlending] = (float) ($reservado[$idBlending] ?? 0) + $cantidad;
        }

        return null;
    }

    /**
     * Guarda en disco las evidencias de cada carga y las adjunta al payload.
     *
     * @param array<int, array<string, mixed>> $cargas
     * @param array<string, array<int, UploadedFile>> $archivosPorCarga
     * @return array<int, array<string, mixed>>
     */
    private static function adjuntar_evidencias(array $cargas, array $archivosPorCarga): array
    {
        foreach ($cargas as $index => &$carga) {
            $archivos = $archivosPorCarga["evidencias_{$index}"] ?? ($archivosPorCarga['evidencias'] ?? []);
            if (!empty($archivos)) {
                $carga['evidencias'] = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_VENTA, $archivos);
            }
        }
        unset($carga);

        return $cargas;
    }
}
