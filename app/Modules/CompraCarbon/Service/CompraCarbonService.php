<?php

namespace App\Modules\CompraCarbon\Service;

use App\Modules\CompraCarbon\Data\CompraCarbonData;
use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class CompraCarbonService
{
    public static function get_compras(array $opts = []): array
    {
        $data = CompraCarbonData::get_compras($opts);
        return ApiResponse::success($data, 'Compras de carbón obtenidas correctamente');
    }

    public static function get_compra_con_detalles(int $id_compra_carbon): array
    {
        $resultado = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($resultado['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        return ApiResponse::success($resultado, 'Compra obtenida correctamente');
    }

    /**
     * Registro preliminar de la compra de carbón.
     * Solo requiere empresa, proveedor y 1 detalle con tipo de carbón y toneladas.
     * Se toma la tarifa con mayor precio para ese tipo de carbón.
     */
    public static function crear_compra(array $payload, int $id_empleado_registro): array
    {
        $id_empresa = (int) ($payload['id_empresa'] ?? 0);
        $id_proveedor = (int) ($payload['id_proveedor'] ?? 0);
        $detallesIn = $payload['detalles'] ?? [];

        if ($id_empresa <= 0 || $id_proveedor <= 0) {
            return ApiResponse::error('Empresa y proveedor son requeridos');
        }
        if (empty($detallesIn)) {
            return ApiResponse::error('La compra preliminar debe indicar el tipo de carbón y la cantidad');
        }

        $primerDetalle = $detallesIn[0];
        $id_tipo = (int) ($primerDetalle['id_tipo_carbon'] ?? 0);
        $cantidad = (float) ($primerDetalle['cantidad'] ?? 0);

        if ($id_tipo <= 0) {
            return ApiResponse::error('Tipo de carbón requerido');
        }
        if ($cantidad <= 0) {
            return ApiResponse::error('La cantidad en toneladas debe ser mayor a 0');
        }

        // Buscar tarifa con precio mayor para ese tipo de carbón
        $tarifaMayor = CompraCarbonData::get_tarifa_max_precio($id_tipo);
        $precioUnitario = $tarifaMayor ? (float) $tarifaMayor->precio_unitario : (float) ($primerDetalle['precio_unitario'] ?? 0);
        $idTarifaCarbon = $tarifaMayor ? (int) $tarifaMayor->id : null;

        $subtotalAntes = round($cantidad * $precioUnitario, 2);
        $totalAntesDescuento = $subtotalAntes;
        $totalConDescuento = $subtotalAntes;

        $fechaHoraIngreso = !empty($payload['fecha_hora_ingreso'])
            ? (string) $payload['fecha_hora_ingreso']
            : now()->toDateTimeString();

        $detallesNorm = [
            [
                'id_tipo_carbon' => $id_tipo,
                'id_transportista' => null,
                'id_lugar_extraccion' => null,
                'id_tarifa_carbon' => $idTarifaCarbon,
                'placa' => '',
                'guia_remitente' => '',
                'guia_transportista' => null,
                'pagar_flete' => false,
                'codigo_ticket_balanza' => '',
                'cantidad' => $cantidad,
                'porcentaje_ceniza' => 0.0,
                'porcentaje_humedad' => 0.0,
                'precio_unitario' => $precioUnitario,
                'costo_flete_por_tonelada' => 0.0,
                'subtotal_antes_descuento' => $subtotalAntes,
                'descuento_flete' => 0.0,
                'subtotal_con_descuento' => $subtotalAntes,
                'evidencias' => null,
                'log_cambios' => null,
            ],
        ];

        return DB::transaction(function () use (
            $id_empresa,
            $id_proveedor,
            $id_empleado_registro,
            $fechaHoraIngreso,
            $totalAntesDescuento,
            $totalConDescuento,
            $detallesNorm
        ) {
            $correlativoData = CorrelativoHelper::generar(
                tabla: 'compra_carbon',
                prefijo: 'CC',
                filtros: ['id_empresa' => $id_empresa],
                longitudCeros: 5,
                reseteo: Periodo::Anual,
                columnaFecha: 'fecha_hora_ingreso',
            );

            $id_compra = CompraCarbonData::insert_cabecera([
                'id_empresa' => $id_empresa,
                'id_proveedor' => $id_proveedor,
                'id_almacen' => null,
                'id_almacen_cliente' => null,
                'id_almacen_proveedor' => null,
                'id_empleado_registro' => $id_empleado_registro,
                'tipo_despacho' => null,
                'aplica_igv' => 0,
                'porcentaje_igv' => 0,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'fecha_hora_ingreso' => $fechaHoraIngreso,
                'total_antes_descuento' => $totalAntesDescuento,
                'monto_igv' => 0.0,
                'descuento_flete' => 0.0,
                'total_con_descuento' => $totalConDescuento,
                'estado' => EstadoCompraCarbon::Preliminar->value,
                'evidencias' => null,
                'created_at' => now()->toDateTimeString(),
            ]);

            CompraCarbonData::insert_detalles($id_compra, $detallesNorm);

            return self::get_compra_con_detalles($id_compra);
        });
    }

    /**
     * Confirma la llegada de la carga de una compra preliminar.
     * Completa tipo_despacho, almacenes, IGV, fecha real, y la lista completa de detalles.
     */
    public static function confirmar_compra(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado_confirma
    ): array {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado !== EstadoCompraCarbon::Preliminar->value) {
            return ApiResponse::error('Solo se puede confirmar una compra en estado Preliminar');
        }

        $resNormalizado = self::validar_y_normalizar_compra_completa($payload);
        if (!$resNormalizado['ok']) {
            return ApiResponse::error($resNormalizado['mensaje']);
        }

        $cabecera = $resNormalizado['cabecera'];
        $detalles = $resNormalizado['detalles'];

        CompraCarbonData::confirmar_compra(
            $id_compra_carbon,
            $cabecera,
            $detalles,
            $id_empleado_confirma
        );

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Edita una compra de carbón mientras no haya sido aprobada su liquidación.
     * Registra los cambios en log_cambios.
     */
    public static function actualizar_compra(
        int $id_compra_carbon,
        array $payload,
        int $id_empleado,
        string $nombre_empleado,
        ?string $motivo = null
    ): array {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }

        $estadoActual = (string) $existente['cabecera']->estado;
        if ($estadoActual === EstadoCompraCarbon::LiquidacionAprobada->value) {
            return ApiResponse::error('No se puede editar una compra cuya liquidación ya fue aprobada');
        }
        if ($estadoActual === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('No se puede editar una compra anulada');
        }

        // Si es preliminar y sigue preliminar, valida flexible; si ya es confirmada, valida completa
        $esPreliminar = ($estadoActual === EstadoCompraCarbon::Preliminar->value);
        if ($esPreliminar && empty($payload['detalles'][0]['precio_unitario']) && count($payload['detalles'] ?? []) === 1) {
            // Edición de preliminar
            $id_empresa = (int) ($payload['id_empresa'] ?? $existente['cabecera']->id_empresa);
            $id_proveedor = (int) ($payload['id_proveedor'] ?? $existente['cabecera']->id_proveedor);
            $detallesIn = $payload['detalles'] ?? [];
            $primerDetalle = $detallesIn[0] ?? [];
            $id_tipo = (int) ($primerDetalle['id_tipo_carbon'] ?? 0);
            $cantidad = (float) ($primerDetalle['cantidad'] ?? 0);

            $tarifaMayor = CompraCarbonData::get_tarifa_max_precio($id_tipo);
            $precioUnitario = $tarifaMayor ? (float) $tarifaMayor->precio_unitario : 0.0;
            $subtotal = round($cantidad * $precioUnitario, 2);

            $cabecera = [
                'id_empresa' => $id_empresa,
                'id_proveedor' => $id_proveedor,
                'tipo_despacho' => null,
                'id_almacen' => null,
                'id_almacen_cliente' => null,
                'id_almacen_proveedor' => null,
                'aplica_igv' => 0,
                'porcentaje_igv' => 0,
                'fecha_hora_ingreso' => $payload['fecha_hora_ingreso'] ?? $existente['cabecera']->fecha_hora_ingreso,
                'total_antes_descuento' => $subtotal,
                'monto_igv' => 0.0,
                'descuento_flete' => 0.0,
                'total_con_descuento' => $subtotal,
            ];

            $detallesNorm = [
                [
                    'id_tipo_carbon' => $id_tipo,
                    'id_transportista' => null,
                    'id_lugar_extraccion' => null,
                    'id_tarifa_carbon' => $tarifaMayor ? (int) $tarifaMayor->id : null,
                    'placa' => '',
                    'guia_remitente' => '',
                    'guia_transportista' => null,
                    'pagar_flete' => false,
                    'codigo_ticket_balanza' => '',
                    'cantidad' => $cantidad,
                    'porcentaje_ceniza' => 0.0,
                    'porcentaje_humedad' => 0.0,
                    'precio_unitario' => $precioUnitario,
                    'costo_flete_por_tonelada' => 0.0,
                    'subtotal_antes_descuento' => $subtotal,
                    'descuento_flete' => 0.0,
                    'subtotal_con_descuento' => $subtotal,
                    'evidencias' => null,
                    'log_cambios' => null,
                ],
            ];

            CompraCarbonData::actualizar_compra(
                $id_compra_carbon,
                $cabecera,
                $detallesNorm,
                $id_empleado,
                $nombre_empleado,
                $motivo
            );

            return self::get_compra_con_detalles($id_compra_carbon);
        }

        $resNormalizado = self::validar_y_normalizar_compra_completa($payload);
        if (!$resNormalizado['ok']) {
            return ApiResponse::error($resNormalizado['mensaje']);
        }

        CompraCarbonData::actualizar_compra(
            $id_compra_carbon,
            $resNormalizado['cabecera'],
            $resNormalizado['detalles'],
            $id_empleado,
            $nombre_empleado,
            $motivo
        );

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Aprueba la liquidación de la orden de compra.
     * Permite asociar o descontar anticipos otorgados previamente al proveedor.
     *
     * @param array<int, array{id_anticipo_proveedor: int, monto_retirado: float}> $anticipos
     */
    public static function aprobar_liquidacion(
        int $id_compra_carbon,
        int $id_empleado_aprueba,
        array $anticipos = []
    ): array {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado !== EstadoCompraCarbon::Confirmado->value) {
            return ApiResponse::error('Solo se puede aprobar la liquidación de compras en estado Confirmado');
        }

        try {
            CompraCarbonData::aprobar_liquidacion(
                $id_compra_carbon,
                $id_empleado_aprueba,
                now()->toDateTimeString(),
                $anticipos
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage());
        }

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Anula una compra mientras no haya sido aprobada su liquidación.
     */
    public static function anular_compra(int $id_compra_carbon, int $id_empleado_anula): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La compra ya está anulada');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::LiquidacionAprobada->value) {
            return ApiResponse::error('No se puede anular una compra cuya liquidación ya fue aprobada');
        }

        CompraCarbonData::anular($id_compra_carbon, $id_empleado_anula);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Guarda evidencias de cabecera.
     */
    public static function set_evidencias(int $id_compra_carbon, array $evidencias): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }

        CompraCarbonData::set_evidencias($id_compra_carbon, $evidencias);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Verifica documentos duplicados (ticket balanza, guía remitente, guía transportista)
     * por proveedor en compras anteriores no anuladas.
     */
    public static function verificar_documentos_duplicados(array $payload): array
    {
        $id_proveedor = (int) ($payload['id_proveedor'] ?? 0);
        if ($id_proveedor <= 0) {
            return ApiResponse::error('Proveedor requerido para validar documentos');
        }

        $tickets = (array) ($payload['tickets'] ?? []);
        $guiasRemitente = (array) ($payload['guias_remitente'] ?? []);
        $guiasTransportista = (array) ($payload['guias_transportista'] ?? []);
        $idIgnorar = isset($payload['id_compra_carbon']) && (int) $payload['id_compra_carbon'] > 0
            ? (int) $payload['id_compra_carbon']
            : null;

        $duplicados = CompraCarbonData::verificar_documentos_duplicados(
            $id_proveedor,
            $tickets,
            $guiasRemitente,
            $guiasTransportista,
            $idIgnorar
        );

        return ApiResponse::success($duplicados, 'Verificación de documentos completada');
    }

    /**
     * Helper privado para validar y normalizar datos completos de una compra confirmada o editada.
     * Soporta costos hasta 6 decimales.
     */
    private static function validar_y_normalizar_compra_completa(array $payload): array
    {
        $id_empresa = (int) ($payload['id_empresa'] ?? 0);
        $id_proveedor = (int) ($payload['id_proveedor'] ?? 0);
        $tipo_despacho_raw = strtolower(trim((string) ($payload['tipo_despacho'] ?? 'envio')));
        $tipo_despacho = ($tipo_despacho_raw === 'recojo') ? 'recojo' : 'envio';

        $id_almacen_proveedor = isset($payload['id_almacen_proveedor']) && (int) $payload['id_almacen_proveedor'] > 0
            ? (int) $payload['id_almacen_proveedor']
            : null;

        $id_almacen = isset($payload['id_almacen']) && (int) $payload['id_almacen'] > 0
            ? (int) $payload['id_almacen']
            : null;

        $id_almacen_cliente = isset($payload['id_almacen_cliente']) && (int) $payload['id_almacen_cliente'] > 0
            ? (int) $payload['id_almacen_cliente']
            : null;

        $aplica_igv = !empty($payload['aplica_igv']);
        $porcentaje_igv = $aplica_igv ? (float) ($payload['porcentaje_igv'] ?? 18) : 0.0;
        $fecha_hora_ingreso = (string) ($payload['fecha_hora_ingreso'] ?? '');
        $detallesIn = (array) ($payload['detalles'] ?? []);

        if ($id_empresa <= 0 || $id_proveedor <= 0) {
            return ['ok' => false, 'mensaje' => 'Empresa y proveedor son requeridos'];
        }

        if ($tipo_despacho === 'recojo' && ($id_almacen_proveedor === null || $id_almacen_proveedor <= 0)) {
            return ['ok' => false, 'mensaje' => 'Debe indicar de qué almacén del proveedor se va a recoger la carga'];
        }

        if ($id_almacen === null && $id_almacen_cliente === null) {
            return ['ok' => false, 'mensaje' => 'Debe seleccionar el almacén de destino (empresa o cliente)'];
        }

        if ($fecha_hora_ingreso === '') {
            return ['ok' => false, 'mensaje' => 'La fecha y hora de ingreso son requeridas'];
        }

        if (empty($detallesIn)) {
            return ['ok' => false, 'mensaje' => 'La compra debe tener al menos una carga (detalle)'];
        }

        $detallesNorm = [];
        $sum_subtotal_antes = 0.0;
        $sum_descuento_flete = 0.0;
        $sum_subtotal_con = 0.0;

        foreach ($detallesIn as $idx => $d) {
            $numItem = $idx + 1;
            $id_tipo = (int) ($d['id_tipo_carbon'] ?? 0);
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $precio = (float) ($d['precio_unitario'] ?? 0);

            if ($id_tipo <= 0) {
                return ['ok' => false, 'mensaje' => "El ítem #{$numItem} requiere un tipo de carbón"];
            }
            if ($cantidad <= 0) {
                return ['ok' => false, 'mensaje' => "El ítem #{$numItem} requiere cantidad en toneladas mayor a 0"];
            }
            if ($precio < 0) {
                return ['ok' => false, 'mensaje' => "El ítem #{$numItem} requiere precio unitario mayor o igual a 0"];
            }

            $pagar_flete = !empty($d['pagar_flete']);
            $costo_flete = (float) ($d['costo_flete_por_tonelada'] ?? 0);
            $id_transportista = isset($d['id_transportista']) && (int) $d['id_transportista'] > 0
                ? (int) $d['id_transportista']
                : null;

            if ($pagar_flete) {
                if ($id_transportista === null || $id_transportista <= 0) {
                    return ['ok' => false, 'mensaje' => "El ítem #{$numItem} requiere transportista porque paga flete"];
                }
                if ($costo_flete <= 0) {
                    return ['ok' => false, 'mensaje' => "El ítem #{$numItem} requiere costo de flete por tonelada mayor a 0"];
                }
            } else {
                $costo_flete = 0.0;
                $id_transportista = null;
            }

            $subtotal_antes = round($cantidad * $precio, 2);
            $descuento_flete = round($cantidad * $costo_flete, 2);
            $subtotal_con = round($subtotal_antes - $descuento_flete, 2);

            $sum_subtotal_antes += $subtotal_antes;
            $sum_descuento_flete += $descuento_flete;
            $sum_subtotal_con += $subtotal_con;

            $detallesNorm[] = [
                'id_tipo_carbon' => $id_tipo,
                'id_transportista' => $id_transportista,
                'id_lugar_extraccion' => isset($d['id_lugar_extraccion']) && (int) $d['id_lugar_extraccion'] > 0
                    ? (int) $d['id_lugar_extraccion']
                    : null,
                'id_tarifa_carbon' => isset($d['id_tarifa_carbon']) && (int) $d['id_tarifa_carbon'] > 0
                    ? (int) $d['id_tarifa_carbon']
                    : null,
                'placa' => isset($d['placa']) ? trim((string) $d['placa']) : '',
                'guia_remitente' => isset($d['guia_remitente']) ? trim((string) $d['guia_remitente']) : '',
                'guia_transportista' => !empty($d['guia_transportista']) ? trim((string) $d['guia_transportista']) : null,
                'pagar_flete' => $pagar_flete,
                'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) ? trim((string) $d['codigo_ticket_balanza']) : '',
                'cantidad' => $cantidad,
                'porcentaje_ceniza' => (float) ($d['porcentaje_ceniza'] ?? 0),
                'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
                'precio_unitario' => round($precio, 6),
                'costo_flete_por_tonelada' => round($costo_flete, 6),
                'subtotal_antes_descuento' => $subtotal_antes,
                'descuento_flete' => $descuento_flete,
                'subtotal_con_descuento' => $subtotal_con,
                'evidencias' => isset($d['evidencias']) && is_array($d['evidencias']) ? $d['evidencias'] : null,
                'log_cambios' => isset($d['log_cambios']) && is_array($d['log_cambios']) ? $d['log_cambios'] : null,
            ];
        }

        $total_antes_descuento = round($sum_subtotal_antes, 2);
        $descuento_flete_total = round($sum_descuento_flete, 2);
        $total_con_descuento = round($sum_subtotal_con, 2);
        $monto_igv = $aplica_igv
            ? round($total_antes_descuento * $porcentaje_igv / 100, 2)
            : 0.0;

        $evidenciasCab = $payload['evidencias'] ?? null;
        if (is_array($evidenciasCab)) {
            $evidenciasCab = json_encode($evidenciasCab, JSON_UNESCAPED_UNICODE);
        }

        return [
            'ok' => true,
            'cabecera' => [
                'id_empresa' => $id_empresa,
                'id_proveedor' => $id_proveedor,
                'tipo_despacho' => $tipo_despacho,
                'id_almacen_proveedor' => $id_almacen_proveedor,
                'id_almacen' => $id_almacen,
                'id_almacen_cliente' => $id_almacen_cliente,
                'aplica_igv' => $aplica_igv,
                'porcentaje_igv' => $porcentaje_igv,
                'fecha_hora_ingreso' => $fecha_hora_ingreso,
                'total_antes_descuento' => $total_antes_descuento,
                'monto_igv' => $monto_igv,
                'descuento_flete' => $descuento_flete_total,
                'total_con_descuento' => $total_con_descuento,
                'evidencias' => $evidenciasCab,
            ],
            'detalles' => $detallesNorm,
        ];
    }
}
