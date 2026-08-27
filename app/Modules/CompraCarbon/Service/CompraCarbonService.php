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
        return ApiResponse::success($data, 'Compras de carbon obtenidas correctamente');
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
     * Aprueba una compra de carbon: setea id_empleado_aprueba,
     * fecha_hora_aprobacion (now) y estado = Aprobado.
     */
    public static function aprobar_compra(int $id_compra_carbon, int $id_empleado_aprueba): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Aprobado->value) {
            return ApiResponse::error('La compra ya fue aprobada');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La compra esta anulada, no se puede aprobar');
        }

        CompraCarbonData::aprobar(
            $id_compra_carbon,
            $id_empleado_aprueba,
            now()->toDateTimeString(),
        );

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Guarda la lista de evidencias de la compra (reemplaza TODAS).
     * Solo permitido si la compra esta aprobada.
     *
     * @param array<int, array{url:string, path_relativo:string, nombre_original:?string, extension:?string}> $evidencias
     */
    public static function set_evidencias(int $id_compra_carbon, array $evidencias): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado !== EstadoCompraCarbon::Aprobado->value) {
            return ApiResponse::error('Solo se pueden subir evidencias a una compra aprobada');
        }

        CompraCarbonData::set_evidencias($id_compra_carbon, $evidencias);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Anula una compra de carbon. Permitido desde Pendiente o Aprobado.
     * Si ya esta Anulada, retorna error.
     */
    public static function anular_compra(int $id_compra_carbon): array
    {
        $existente = CompraCarbonData::get_compra_con_detalles($id_compra_carbon);
        if ($existente['cabecera'] === null) {
            return ApiResponse::error('La compra no existe');
        }
        if ($existente['cabecera']->estado === EstadoCompraCarbon::Anulado->value) {
            return ApiResponse::error('La compra ya esta anulada');
        }

        CompraCarbonData::anular($id_compra_carbon);

        return self::get_compra_con_detalles($id_compra_carbon);
    }

    /**
     * Crea una compra de carbon (cabecera + N detalles) en transaccion.
     *
     * Cabecera:
     *   id_empresa, id_proveedor, id_almacen,
     *   aplica_igv, porcentaje_igv,
     *   fecha_hora_ingreso.
     *
     * Detalle (por item):
     *   id_tipo_carbon, id_transportista?, id_lugar_extraccion?, id_tarifa_carbon?,
     *   placa?, guia_remitente?, guia_transportista?, pagar_flete,
     *   codigo_ticket_balanza?, cantidad, porcentaje_ceniza, porcentaje_humedad,
     *   precio_unitario, costo_flete_por_tonelada, evidencias?.
     *
     * Totales calculados server-side:
     *   - cabecera.total_antes_descuento = sum(d.subtotal_antes_descuento)
     *   - cabecera.monto_igv = aplica_igv ? total_antes_descuento * porcentaje_igv/100 : 0
     *   - cabecera.descuento_flete = sum(d.descuento_flete)
     *   - cabecera.total_con_descuento = sum(d.subtotal_con_descuento)  // se paga al proveedor
     *   - d.subtotal_antes_descuento = cantidad * precio_unitario
     *   - d.descuento_flete = cantidad * costo_flete_por_tonelada
     *   - d.subtotal_con_descuento = d.subtotal_antes_descuento - d.descuento_flete
     *
     * Reglas:
     *   - Si pagar_flete = true, id_transportista y costo_flete_por_tonelada > 0 son obligatorios.
     */
    public static function crear_compra(array $payload, int $id_empleado_registro): array
    {
        $id_empresa = (int) ($payload['id_empresa'] ?? 0);
        $id_proveedor = (int) ($payload['id_proveedor'] ?? 0);
        $id_almacen = isset($payload['id_almacen']) && $payload['id_almacen'] !== null
            ? (int) $payload['id_almacen']
            : 0;
        $aplica_igv = !empty($payload['aplica_igv']);
        $porcentaje_igv = (float) ($payload['porcentaje_igv'] ?? 0);
        $fecha_hora_ingreso = (string) ($payload['fecha_hora_ingreso'] ?? '');
        /** @var array<int, array<string, mixed>> $detallesIn */
        $detallesIn = $payload['detalles'] ?? [];

        if ($id_empresa <= 0 || $id_proveedor <= 0) {
            return ApiResponse::error('Empresa y proveedor son requeridos');
        }
        if ($id_almacen <= 0) {
            return ApiResponse::error('Almacen requerido');
        }
        if (empty($detallesIn)) {
            return ApiResponse::error('La compra debe tener al menos un item');
        }
        if ($fecha_hora_ingreso === '') {
            return ApiResponse::error('La fecha y hora de ingreso son requeridas');
        }

        // Si no aplica IGV forzamos porcentaje 0.
        if (!$aplica_igv) {
            $porcentaje_igv = 0.0;
        }
        if ($porcentaje_igv < 0 || $porcentaje_igv > 100) {
            return ApiResponse::error('El porcentaje de IGV debe estar entre 0 y 100');
        }

        // Normalizamos cada detalle y calculamos sus subtotales.
        $detallesNorm = [];
        $sum_subtotal_antes = 0.0;
        $sum_descuento_flete = 0.0;
        $sum_subtotal_con = 0.0;
        foreach ($detallesIn as $idx => $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $precio = (float) ($d['precio_unitario'] ?? 0);
            $id_tipo = (int) ($d['id_tipo_carbon'] ?? 0);
            if ($id_tipo <= 0 || $cantidad <= 0 || $precio < 0) {
                return ApiResponse::error('Todos los items deben tener tipo de carbon, cantidad > 0 y precio >= 0');
            }

            $pagar_flete = !empty($d['pagar_flete']);
            $costo_flete = (float) ($d['costo_flete_por_tonelada'] ?? 0);
            $id_transportista = isset($d['id_transportista']) && $d['id_transportista'] !== null
                ? (int) $d['id_transportista']
                : null;

            if ($pagar_flete) {
                if ($id_transportista === null || $id_transportista <= 0) {
                    return ApiResponse::error('El item #' . ($idx + 1) . ' requiere transportista porque paga flete');
                }
                if ($costo_flete <= 0) {
                    return ApiResponse::error('El item #' . ($idx + 1) . ' requiere costo de flete por tonelada > 0');
                }
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
                'id_lugar_extraccion' => isset($d['id_lugar_extraccion']) && $d['id_lugar_extraccion'] !== null
                    ? (int) $d['id_lugar_extraccion']
                    : null,
                'id_tarifa_carbon' => isset($d['id_tarifa_carbon']) && $d['id_tarifa_carbon'] !== null
                    ? (int) $d['id_tarifa_carbon']
                    : null,
                'placa' => isset($d['placa']) ? (string) $d['placa'] : '',
                'guia_remitente' => isset($d['guia_remitente']) ? (string) $d['guia_remitente'] : '',
                'guia_transportista' => isset($d['guia_transportista']) && $d['guia_transportista'] !== ''
                    ? (string) $d['guia_transportista']
                    : null,
                'pagar_flete' => $pagar_flete,
                'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) ? (string) $d['codigo_ticket_balanza'] : '',
                'cantidad' => $cantidad,
                'porcentaje_ceniza' => (float) ($d['porcentaje_ceniza'] ?? 0),
                'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
                'precio_unitario' => $precio,
                'costo_flete_por_tonelada' => $costo_flete,
                'subtotal_antes_descuento' => $subtotal_antes,
                'descuento_flete' => $descuento_flete,
                'subtotal_con_descuento' => $subtotal_con,
                'evidencias' => isset($d['evidencias']) && is_array($d['evidencias']) ? $d['evidencias'] : null,
            ];
        }

        $total_antes_descuento = round($sum_subtotal_antes, 2);
        $descuento_flete_total = round($sum_descuento_flete, 2);
        $total_con_descuento = round($sum_subtotal_con, 2);
        $monto_igv = $aplica_igv
            ? round($total_antes_descuento * $porcentaje_igv / 100, 2)
            : 0.0;

        return DB::transaction(function () use (
            $id_empresa,
            $id_proveedor,
            $id_almacen,
            $id_empleado_registro,
            $aplica_igv,
            $porcentaje_igv,
            $fecha_hora_ingreso,
            $detallesNorm,
            $total_antes_descuento,
            $descuento_flete_total,
            $total_con_descuento,
            $monto_igv,
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
                'id_almacen' => $id_almacen,
                'id_empleado_registro' => $id_empleado_registro,
                'aplica_igv' => $aplica_igv,
                'porcentaje_igv' => $porcentaje_igv,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'fecha_hora_ingreso' => $fecha_hora_ingreso,
                'total_antes_descuento' => $total_antes_descuento,
                'monto_igv' => $monto_igv,
                'descuento_flete' => $descuento_flete_total,
                'total_con_descuento' => $total_con_descuento,
                'estado_pago' => null,
                'estado' => EstadoCompraCarbon::Pendiente->value,
                'created_at' => now()->toDateTimeString(),
            ]);

            CompraCarbonData::insert_detalles($id_compra, $detallesNorm);

            return self::get_compra_con_detalles($id_compra);
        });
    }
}
