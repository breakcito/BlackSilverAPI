<?php

namespace App\Modules\VentaCarbon\Service;

use App\Modules\VentaCarbon\Data\VentaCarbonData;
use App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon;
use App\Shared\Enums\VentaCarbon\TipoVehiculoCarbon;
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
     * Obtiene una venta con el detalle de sus cargas y su liquidacion.
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

        $idTipoConfirmado = (int) ($payload['id_tipo_carbon'] ?? 0);
        if ($idTipoConfirmado <= 0) {
            return ApiResponse::error('Debe indicar el tipo de carbon confirmado');
        }

        $tipoCarbon = VentaCarbonData::get_tipo_carbon($idTipoConfirmado);
        if ($tipoCarbon === null) {
            return ApiResponse::error('El tipo de carbon confirmado no existe');
        }

        $calidad = (string) ($payload['calidad'] ?? '');
        if (!in_array($calidad, VentaCarbonData::get_calidades(), true)) {
            return ApiResponse::error('Debe indicar la calidad del carbon que llego');
        }

        $idVehiculo = (int) ($payload['id_vehiculo_carbon'] ?? 0);
        if ($idVehiculo <= 0) {
            return ApiResponse::error('Debe indicar el vehiculo que transporto la carga');
        }

        $vehiculo = VentaCarbonData::get_vehiculo_carbon($idVehiculo);
        if ($vehiculo === null) {
            return ApiResponse::error('El vehiculo seleccionado no existe');
        }

        $tipoVehiculo = TipoVehiculoCarbon::tryFrom((string) $vehiculo->tipo_vehiculo);
        $segundaPlaca = trim((string) ($payload['segunda_placa'] ?? ''));
        if ($tipoVehiculo !== null && $tipoVehiculo->exige_segunda_placa() && $segundaPlaca === '') {
            return ApiResponse::error("El vehiculo es {$tipoVehiculo->value}, debe ingresar la segunda placa");
        }

        if (empty($archivos)) {
            $evidencias = $payload['evidencias'] ?? null;
        } else {
            $evidencias = ArchivoHelper::guardarArchivos(self::CARPETA_EVIDENCIAS_VENTA, $archivos);
        }

        try {
            VentaCarbonData::confirmar_llegada($id_venta_carbon, $id_carga_venta_carbon, [
                'id_tipo_carbon' => $idTipoConfirmado,
                'nombre_tipo_carbon' => (string) $tipoCarbon->nombre,
                'calidad' => $calidad,
                'cantidad_toneladas' => (float) $payload['cantidad_toneladas'],
                'fecha_hora_ingreso' => (string) $payload['fecha_hora_ingreso'],
                'id_vehiculo_carbon' => $idVehiculo,
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
     * Stock disponible de carbon en un almacen de salida.
     *
     * Considera las cargas ya despachadas y pendientes de confirmar: esas
     * toneladas estan comprometidas y no se pueden volver a despachar.
     */
    public static function get_stock_disponible(int $id_almacen): array
    {
        $stocks = \App\Data\StockCarbonData::get_stocks(['id_almacen' => $id_almacen]);

        $salida = [];
        foreach ($stocks as $stock) {
            $idTipo = (int) ($stock->id_tipo_carbon ?? 0);
            $calidad = (string) ($stock->calidad ?? '');
            if ($idTipo <= 0 || $calidad === '') {
                continue;
            }

            $salida[] = [
                'id_tipo_carbon' => $idTipo,
                'calidad' => $calidad,
                'stock_actual' => (float) ($stock->stock_actual ?? 0),
                'disponible' => VentaCarbonData::calcular_stock_disponible(
                    $id_almacen,
                    $idTipo,
                    $calidad
                ),
            ];
        }

        return $salida;
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
     * Al despachar solo se indica el tipo de carbon, la calidad, la fecha y
     * hora de salida, las toneladas y las evidencias. El vehiculo y la
     * segunda placa se registran al confirmar la llegada.
     *
     * @param array<int, array<string, mixed>> $cargas
     */
    private static function validar_cargas(array $cargas): ?string
    {
        // El catalogo de calidades es el fuente de verdad: puede tener mas
        // calidades que el enum `TipoCalidadCarbon`.
        $valoresCalidad = VentaCarbonData::get_calidades();

        foreach ($cargas as $i => $carga) {
            $n = $i + 1;

            $idTipo = (int) ($carga['id_tipo_carbon'] ?? 0);
            if ($idTipo <= 0) {
                return "Carga #{$n}: debe seleccionar el tipo de carbon";
            }

            $cantidad = (float) ($carga['cantidad_toneladas'] ?? 0);
            if ($cantidad <= 0) {
                return "Carga #{$n}: la cantidad de toneladas debe ser mayor a 0";
            }

            $calidad = (string) ($carga['calidad'] ?? '');
            if (!in_array($calidad, $valoresCalidad, true)) {
                return "Carga #{$n}: debe indicar la calidad del carbon a despachar";
            }

            if (empty($carga['fecha_hora_salida'])) {
                return "Carga #{$n}: debe indicar la fecha y hora de salida";
            }
        }

        return null;
    }

    /**
     * Verifica que el almacen de salida tenga stock suficiente para las cargas.
     *
     * El stock no se descuenta al despachar (el asiento se escribe al
     * confirmar la llegada), asi que se valida contra el stock fisico menos
     * las cargas que ya salieron y siguen pendientes de confirmar.
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
            $idTipo = (int) ($carga['id_tipo_carbon'] ?? 0);
            $calidad = (string) ($carga['calidad'] ?? '');
            $cantidad = (float) ($carga['cantidad_toneladas'] ?? 0);

            $clave = $idTipo . '|' . $calidad;

            $disponible = VentaCarbonData::calcular_stock_disponible(
                $id_almacen_salida,
                $idTipo,
                $calidad
            );
            $disponible -= (float) ($reservado[$clave] ?? 0);

            if ($disponible + 0.0001 < $cantidad) {
                return "Carga #{$n}: no hay stock suficiente de la calidad {$calidad} en el almacen de salida "
                    . '(disponible: '
                    . rtrim(rtrim(number_format($disponible, 2), '0'), '.') . ' TN)';
            }

            $reservado[$clave] = (float) ($reservado[$clave] ?? 0) + $cantidad;
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
