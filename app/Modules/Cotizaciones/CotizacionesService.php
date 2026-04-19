<?php

namespace App\Modules\Cotizaciones;

use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\Cotizacion\EstadoCotizacion;
use App\Shared\Enums\Cotizacion\EstadoCotizacionDetalle;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalleLog;
use App\Modules\Cotizaciones\Data\CotizacionesData;
use App\Modules\Cotizaciones\Data\ProductosData;
use App\Modules\Cotizaciones\Data\ProveedoresData;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\OrdenCompraDetalleLog;
use App\Shared\Enums\_Generic\MetodoPago;
use Illuminate\Support\Facades\DB;

class CotizacionesService
{
    /**
     * Registrar un comparativo con sus cotizaciones y detalles
     * 
     * @param array $productos Listado de productos a comparar
     * @param array $cotizaciones Listado de cotizaciones de proveedores
     */
    public static function registrar_comparativo(array $productos, array $cotizaciones): array
    {
        if (empty($productos)) return ApiResponse::error('Debe incluir al menos un producto en el comparativo.');
        if (empty($cotizaciones)) return ApiResponse::error('Debe incluir al menos una cotización.');

        // Validar que cada cotización tenga al menos un detalle y empresas
        foreach ($cotizaciones as $c) {
            if (empty($c['detalles'])) {
                return ApiResponse::error('Cada cotización debe incluir al menos un producto a cotizar.');
            }
            if (empty($c['empresas_ids']) || !is_array($c['empresas_ids'])) {
                return ApiResponse::error('Cada cotización debe tener al menos una empresa asociada.');
            }
        }

        try {
            return DB::transaction(function () use ($productos, $cotizaciones) {
                $fecha_ahora = now()->toDateTimeString();

                // 1. Crear el Comparativo Maestro
                $id_comparativo = CotizacionesData::crear_comparativo($fecha_ahora);

                // 2. Crear los Detalles del Comparativo (Productos)
                // Usaremos un mapa para vincular id_producto -> id_comparativo_detalle
                $mapa_productos = [];
                foreach ($productos as $p) {
                    $id_det = CotizacionesData::crear_comparativo_detalle(
                        $id_comparativo,
                        (int)$p['id_producto'],
                        isset($p['id_solicitud_detalle']) ? (int)$p['id_solicitud_detalle'] : null
                    );
                    $mapa_productos[$p['id_producto']] = $id_det;
                }

                $ids_aprobadas = [];
                $cotizaciones_ids = [];

                // 4. Registrar cada Cotización
                foreach ($cotizaciones as $index => $c) {
                    $correlativoData = CotizacionesData::get_nuevo_correlativo();
                    $correlativo = $correlativoData['correlativo'];
                    $numero      = $correlativoData['numero_correlativo'];

                    // Determinar estado final (respetamos lo que viene del front directamente)
                    $estado_final = $c['estado'] ?? EstadoCotizacion::Generada->value;

                    $id_cotizacion = CotizacionesData::crear_cotizacion([
                        'id_comparativo'         => $id_comparativo,
                        'id_proveedor'           => (int)$c['id_proveedor'],
                        'moneda'                 => (string)$c['moneda'],
                        'correlativo'            => $correlativo,
                        'numero_correlativo'     => $numero,
                        'metodo_pago'            => (string)$c['metodo_pago'],
                        'fecha_vencimiento_pago' => (trim((string)($c['metodo_pago'] ?? '')) === MetodoPago::Credito->value)
                            ? ($c['fecha_vencimiento_pago'] ?? null)
                            : null,
                        'total_antes_igv'        => (float)$c['total_antes_igv'],
                        'incluye_igv'            => (bool)$c['incluye_igv'],
                        'porcentaje_igv'         => (float)$c['porcentaje_igv'],
                        'monto_igv'              => (float)$c['monto_igv'],
                        'total_despues_igv'      => (float)$c['total_despues_igv'],
                        'observacion'            => $c['observacion'] ?? null,
                        'evidencias'              => $c['evidencias'] ?? null,
                        'fecha_hora_cotizacion'  => $fecha_ahora,
                        'estado'                 => $estado_final,
                        'created_at'             => $fecha_ahora,
                    ]);

                    if ($estado_final === EstadoCotizacion::Aprobada->value) {
                        $ids_aprobadas[] = [
                            'id' => $id_cotizacion,
                            'correlativo' => $correlativo
                        ];
                    }

                    $detalles_insertados = [];

                    // 5. Asignar empresas a la cotización
                    CotizacionesData::asignar_empresas_cotizacion($id_cotizacion, $c['empresas_ids']);

                    // 6. Registrar Detalles de la Cotización
                    foreach ($c['detalles'] as $det) {
                        $id_comp_det = $mapa_productos[$det['id_producto']] ?? null;

                        if ($id_comp_det) {
                            $id_cot_det = CotizacionesData::crear_cotizacion_detalle([
                                'id_cotizacion'              => $id_cotizacion,
                                'id_comparativo_detalle'     => $id_comp_det,
                                'id_unidad_medida'           => (int)$det['id_unidad_medida'],
                                'cantidad'                   => (float)$det['cantidad'],
                                'contenido_por_presentacion' => (float)$det['contenido_por_presentacion'],
                                'cantidad_base'              => (float)$det['cantidad_base'],
                                'precio_unitario'            => (float)$det['precio_unitario'],
                                'precio_unitario_base'       => (float)$det['precio_unitario_base'],
                                'comentario'                 => $det['comentario'] ?? null,
                                'estado'                     => $det['estado'] ?? EstadoCotizacionDetalle::Pendiente->value,
                            ]);
                            $detalles_insertados[] = [
                                'id_producto' => $det['id_producto'],
                                'id_cot_det' => $id_cot_det
                            ];
                        }
                    }

                    $cotizaciones_ids[] = [
                        'index' => $index,
                        'id' => $id_cotizacion,
                        'correlativo' => $correlativo,
                        'detalles_map' => $detalles_insertados
                    ];
                }

                return ApiResponse::success([
                    'id_comparativo'   => $id_comparativo,
                    'ids_aprobadas'    => $ids_aprobadas,
                    'cotizaciones_ids' => $cotizaciones_ids
                ], 'Comparativo y cotizaciones registrados correctamente.');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Error al registrar el comparativo: ' . $e->getMessage());
        }
    }

    public static function aprobar_cotizacion_parcial(
        int $id_cotizacion,
        int $id_empresa_compradora,
        int $id_empleado,
        array $detalles_aprobados
    ): array {
        try {
            return DB::transaction(function () use ($id_cotizacion, $id_empresa_compradora, $id_empleado, $detalles_aprobados) {
                // 1. Aprobar la cotización principal
                DB::table('cotizacion')
                    ->where('id', $id_cotizacion)
                    ->update(['estado' => EstadoCotizacion::Aprobada->value]);

                // 2. Marcar Detalles como Aprobados / Rechazados
                DB::table('cotizacion_detalle')
                    ->where('id_cotizacion', $id_cotizacion)
                    ->whereIn('id', $detalles_aprobados)
                    ->update(['estado' => EstadoCotizacionDetalle::Aprobado->value]);

                DB::table('cotizacion_detalle')
                    ->where('id_cotizacion', $id_cotizacion)
                    ->whereNotIn('id', $detalles_aprobados)
                    ->update(['estado' => EstadoCotizacionDetalle::Rechazado->value]);

                // 3. Obtener datos de la cotización para la OC
                $cotizacion = DB::table('cotizacion')->where('id', $id_cotizacion)->first();

                // 4. Calcular totales solo de los ítems aprobados
                $pct_igv = $cotizacion->incluye_igv ? (float)$cotizacion->porcentaje_igv : 0;
                $multiplicador = 1 + ($pct_igv / 100);

                $totales = DB::table('cotizacion_detalle')
                    ->selectRaw("
                        SUM(cantidad * precio_unitario) AS total_antes_igv,
                        SUM(cantidad * precio_unitario * {$multiplicador}) AS total_despues_igv
                    ")
                    ->where('id_cotizacion', $id_cotizacion)
                    ->whereIn('id', $detalles_aprobados)
                    ->first();

                $total_antes   = round((float)($totales->total_antes_igv ?? 0), 2);
                $total_despues = round((float)($totales->total_despues_igv ?? 0), 2);
                $monto_igv     = round($total_despues - $total_antes, 2);

                // 5. Correlativo y cabecera de la OC
                $correlativoData = OrdenCompra::get_nuevo_correlativo();

                $id_orden = OrdenCompra::crear_orden(
                    id_cotizacion:         $id_cotizacion,
                    id_empresa:            $id_empresa_compradora,
                    correlativo:           $correlativoData['correlativo'],
                    numero_correlativo:    $correlativoData['numero_correlativo'],
                    fecha_hora_orden:      now()->toDateTimeString(),
                    moneda:                $cotizacion->moneda,
                    incluye_igv:           (bool)$cotizacion->incluye_igv,
                    porcentaje_igv:        (float)$cotizacion->porcentaje_igv,
                    monto_igv:             $monto_igv,
                    total_antes_igv:       $total_antes,
                    total_despues_igv:     $total_despues,
                );

                // 6. Crear detalles de OC y log inicial por ítem
                $detalles_cot = DB::table('cotizacion_detalle')
                    ->join('comparativo_detalle as cpd', 'cpd.id', '=', 'cotizacion_detalle.id_comparativo_detalle')
                    ->whereIn('cotizacion_detalle.id', $detalles_aprobados)
                    ->select(
                        'cotizacion_detalle.id',
                        'cotizacion_detalle.id_unidad_medida',
                        'cotizacion_detalle.cantidad',
                        'cotizacion_detalle.contenido_por_presentacion',
                        'cotizacion_detalle.cantidad_base',
                        'cpd.id_producto'
                    )
                    ->get();

                foreach ($detalles_cot as $det) {
                    $id_oc_det = OrdenCompraDetalle::crear_detalle(
                        id_orden_compra:            $id_orden,
                        id_cotizacion_detalle:      $det->id,
                        id_producto:                $det->id_producto,
                        id_unidad_medida:           $det->id_unidad_medida,
                        contenido_por_presentacion: (float)$det->contenido_por_presentacion,
                        cantidad_requerida:         (float)$det->cantidad,
                        cantidad_requerida_base:    (float)$det->cantidad_base,
                    );

                    OrdenCompraDetalleLog::crear_log(
                        id_orden_compra_detalle: $id_oc_det,
                        id_empleado:             $id_empleado,
                        estado:                  EstadoOrdenCompraDetalleLog::Pendiente,
                    );
                }

                return ApiResponse::success(
                    ['id_orden_compra' => $id_orden, 'correlativo' => $correlativoData['correlativo']],
                    'Cotización aprobada y Orden de Compra generada correctamente.'
                );
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Error al aprobar: ' . $e->getMessage());
        }
    }

    /**
     * Listar cotizaciones agrupadas con sus detalles
     */
    public static function listar(): array
    {
        $result = CotizacionesData::get_listado_agrupado();
        return ApiResponse::success($result);
    }

    /**
     * Obtener unidades de medida (Desde capa compartida)
     */
    public static function get_unidades_medida(): array
    {
        $unidades = \App\Data\UnidadesMedidaData::get_unidades();
        return ApiResponse::success($unidades);
    }

    /**
     * Obtener productos (Desde capa local de la vista)
     */
    public static function get_productos(): array
    {
        $productos = ProductosData::get_productos_maestro();
        return ApiResponse::success($productos);
    }

    /**
     * Obtener proveedores (Desde capa local de la vista)
     */
    public static function get_proveedores(): array
    {
        $proveedores = ProveedoresData::get_proveedores_maestro();
        return ApiResponse::success($proveedores);
    }
}
