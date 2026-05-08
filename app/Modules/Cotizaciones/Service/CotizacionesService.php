<?php

namespace App\Modules\Cotizaciones\Service;

use App\Modules\Cotizaciones\Data\OrdenesCompraData;
use App\Modules\Cotizaciones\Data\ComparativoData;
use App\Modules\Cotizaciones\Data\CotizacionesData;
use App\Shared\Enums\_Generic\MetodoPago;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoDespachoCompra;
use App\Shared\Enums\Cotizacion\EstadoCotizacion;
use App\Shared\Enums\Cotizacion\EstadoCotizacionDetalle;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalleLog;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class CotizacionesService
{
    /**
     * Registrar un comparativo con sus cotizaciones y detalles
     *
     * @param array $productos Listado de productos a comparar.
     *   Cada ítem: { id_producto: int, id_solicitud_detalle?: int|null }
     *
     * @param array $cotizaciones Listado de cotizaciones de proveedores.
     *   Cada ítem: {
     *     id_proveedor: int, moneda: string, metodo_pago: string,
     *     fecha_vencimiento_pago?: string|null,
     *     costo_flete: float, otros_gastos: float,
     *     total_antes_igv: float, incluye_igv: bool,
     *     porcentaje_igv: float, monto_igv: float, total_despues_igv: float,
     *     observacion?: string|null, evidencias?: string|null, estado: string,
     *     empresas_ids: int[],
     *     detalles: [{
     *       id_producto: int, id_unidad_medida: int,
     *       id_almacen_recepcionista: int, tipo_despacho: string,
     *       lugar_recojo?: string|null, tiempo_entrega: int,
     *       tiempo_entrega_periodo: string, tiempo_entrega_dias: int,
     *       cantidad: float, contenido_por_presentacion: float, cantidad_base: float,
     *       precio_unitario: float, precio_unitario_base: float, comentario?: string|null,
     *       estado?: string|null
     *     }]
     *   }
     */
    public static function registrar_comparativo(
        array $productos,
        array $cotizaciones,
        int $id_empleado
    ): array {
        try {
            return DB::transaction(function () use ($productos, $cotizaciones, $id_empleado) {

                // 1. Crear el comparativo maestro
                $correlativo_comparativo = ComparativoData::get_nuevo_correlativo();
                $id_comparativo = ComparativoData::crear_comparativo($correlativo_comparativo['numero_correlativo']);

                // 2. Crear los productos del comparativo y construir mapa indice_lista → id_comparativo_detalle
                $mapa_productos = [];
                foreach ($productos as $index => $p) {
                    $id_det = ComparativoData::crear_comparativo_detalle(
                        id_comparativo: $id_comparativo,
                        id_producto: (int) $p['id_producto'],
                        id_solicitud_detalle: isset($p['id_solicitud_detalle']) ? (int) $p['id_solicitud_detalle'] : null,
                    );
                    $mapa_productos[$index] = $id_det;
                }

                // Determinar si alguna cotización del comparativo es auditable basado en los productos
                $ids_productos_comparativo = array_column($productos, 'id_producto');
                $es_auditable_general = \Illuminate\Support\Facades\DB::table('producto')
                    ->whereIn('id', $ids_productos_comparativo)
                    ->where('es_auditable', 1)
                    ->exists();

                // 3. Registrar cada cotización con su estado final real
                foreach ($cotizaciones as $c) {
                    $correlativoData = CotizacionesData::get_nuevo_correlativo();

                    $es_credito = trim((string) $c['metodo_pago']) === MetodoPago::Credito->value;
                    $estado_final = EstadoCotizacion::tryFrom($c['estado'] ?? '') ?? EstadoCotizacion::Generada;

                    $id_cotizacion = CotizacionesData::crear_cotizacion(
                        id_comparativo: $id_comparativo,
                        id_proveedor: (int) $c['id_proveedor'],
                        correlativo: $correlativoData['correlativo'],
                        numero_correlativo: (int) $correlativoData['numero_correlativo'],
                        fecha_hora_cotizacion: now()->toDateTimeString(),
                        metodo_pago: (string) $c['metodo_pago'],
                        moneda: (string) $c['moneda'],
                        tipo_cambio_venta_referencial: isset($c['tipo_cambio_venta_referencial']) ? (float) $c['tipo_cambio_venta_referencial'] : null,
                        es_auditable: $es_auditable_general,
                        costo_flete: (float) ($c['costo_flete'] ?? 0),
                        otros_gastos: (float) ($c['otros_gastos'] ?? 0),
                        total_antes_igv: (float) $c['total_antes_igv'],
                        incluye_igv: (bool) $c['incluye_igv'],
                        porcentaje_igv: (float) $c['porcentaje_igv'],
                        monto_igv: (float) $c['monto_igv'],
                        total_despues_igv: (float) $c['total_despues_igv'],
                        observacion: $c['observacion'] ?? null,
                        fecha_vencimiento_pago: $es_credito ? ($c['fecha_vencimiento_pago'] ?? null) : null,
                        evidencias: $c['evidencias'] ?? null,
                        estado: $estado_final,
                    );

                    // 4. Asignar empresas compradoras
                    CotizacionesData::asignar_empresas($id_cotizacion, $c['empresas_ids']);

                    // 5. Registrar detalles con su estado final real
                    $detalles_aprobados_ids = [];
                    foreach ($c['detalles'] as $index => $det) {
                        $id_comp_det = $mapa_productos[$index] ?? null;
                        if ($id_comp_det === null)
                            continue;

                        $tipo_despacho = TipoDespachoCompra::from((string) $det['tipo_despacho']);
                        $periodo = Periodo::from((string) $det['tiempo_entrega_periodo']);
                        $estado_det = EstadoCotizacionDetalle::tryFrom($det['estado'] ?? '') ?? EstadoCotizacionDetalle::Pendiente;

                        $id_cot_det = CotizacionesData::crear_detalle(
                            id_cotizacion: $id_cotizacion,
                            id_comparativo_detalle: $id_comp_det,
                            id_unidad_medida: (int) $det['id_unidad_medida'],
                            id_almacen_recepcionista: (int) $det['id_almacen_recepcionista'],
                            tipo_despacho: $tipo_despacho,
                            lugar_recojo: $tipo_despacho === TipoDespachoCompra::Recojo
                                ? ($det['lugar_recojo'] ?? null)
                                : null,
                            tiempo_entrega: (int) $det['tiempo_entrega'],
                            tiempo_entrega_periodo: $periodo,
                            tiempo_entrega_dias: (int) $det['tiempo_entrega_dias'],
                            cantidad: (float) $det['cantidad'],
                            contenido_por_presentacion: (float) $det['contenido_por_presentacion'],
                            cantidad_base: (float) $det['cantidad_base'],
                            precio_unitario: (float) ($det['precio_unitario'] ?? 0),
                            precio_unitario_base: (float) ($det['precio_unitario_base'] ?? 0),
                            comentario: $det['comentario'] ?? null,
                            estado: $estado_det,
                        );

                        if ($estado_det === EstadoCotizacionDetalle::Aprobado) {
                            $detalles_aprobados_ids[] = $id_cot_det;
                        }
                    }

                    // 6. Si la cotización es Aprobada → crear Orden de Compra automáticamente
                    if ($estado_final === EstadoCotizacion::Aprobada && count($detalles_aprobados_ids) > 0) {
                        $id_empresa_compradora = (int) ($c['id_empresa_compradora'] ?? 0);

                        if ($id_empresa_compradora > 0) {
                            // Obtener los detalles recién insertados para calcular totales de la OC
                            $detalles_cot = CotizacionesData::get_detalles_cotizacion(ids_cotizaciones: $id_cotizacion);
                            $detalles_aprobados_data = array_filter(
                                is_array($detalles_cot) ? $detalles_cot : iterator_to_array($detalles_cot),
                                fn($d) => in_array($d->id_cotizacion_detalle, $detalles_aprobados_ids)
                            );

                            $subtotal = array_sum(array_map(
                                fn($d) => (float) $d->cantidad * (float) $d->precio_unitario,
                                $detalles_aprobados_data
                            ));

                            $costo_flete = (float) ($c['costo_flete'] ?? 0);
                            $otros_gastos = (float) ($c['otros_gastos'] ?? 0);
                            $base = $subtotal + $costo_flete + $otros_gastos;

                            $incluye_igv = (bool) ($c['incluye_igv'] ?? false);
                            $pct_igv = (float) ($c['porcentaje_igv'] ?? 18);

                            if ($incluye_igv) {
                                $factor = 1 + ($pct_igv / 100);
                                $total_antes = round($base / $factor, 2);
                                $monto_igv = round($base - $total_antes, 2);
                                $total_despues = round($base, 2);
                            } else {
                                $total_antes = round($base, 2);
                                $monto_igv = round($base * ($pct_igv / 100), 2);
                                $total_despues = round($base + $monto_igv, 2);
                            }

                            $correlativoOC = OrdenesCompraData::get_nuevo_correlativo();

                            $id_orden = OrdenesCompraData::crear_orden(
                                id_cotizacion: $id_cotizacion,
                                id_empresa: $id_empresa_compradora,
                                id_proveedor: (int) $c['id_proveedor'],
                                correlativo: $correlativoOC['correlativo'],
                                numero_correlativo: (int) $correlativoOC['numero_correlativo'],
                                fecha_hora_orden: now()->toDateTimeString(),
                                moneda: (string) $c['moneda'],
                                tipo_cambio_aplicado: $c['moneda'] !== 'Soles' ? (isset($c['tipo_cambio_aplicado_oc']) ? (float) $c['tipo_cambio_aplicado_oc'] : (isset($c['tipo_cambio_venta_referencial']) ? (float) $c['tipo_cambio_venta_referencial'] : null)) : 1,
                                es_auditable: $es_auditable_general ? 1 : 0,
                                metodo_pago: (string) $c['metodo_pago'],
                                incluye_igv: (bool) $c['incluye_igv'],
                                porcentaje_igv: (float) $c['porcentaje_igv'],
                                monto_igv: $monto_igv,
                                costo_flete: $costo_flete,
                                otros_gastos: $otros_gastos,
                                total_antes_igv: $total_antes,
                                total_despues_igv: $total_despues,
                                fecha_vencimiento_pago: $es_credito ? ($c['fecha_vencimiento_pago'] ?? null) : null,
                            );

                            // Crear detalles de OC y logs
                            foreach ($detalles_aprobados_data as $det) {
                                $tipo_despacho_oc = TipoDespachoCompra::from($det->tipo_despacho);
                                $periodo_oc = Periodo::from($det->tiempo_entrega_periodo);

                                $id_oc_det = OrdenesCompraData::crear_detalle_orden(
                                    id_orden_compra: $id_orden,
                                    id_cotizacion_detalle: $det->id_cotizacion_detalle,
                                    id_producto: (int) $det->id_producto,
                                    id_unidad_medida: (int) $det->id_unidad_medida_ctz,
                                    id_almacen_recepcionista: (int) $det->id_almacen_recepcionista,
                                    tipo_despacho: $tipo_despacho_oc,
                                    tiempo_entrega: (int) $det->tiempo_entrega,
                                    tiempo_entrega_periodo: $periodo_oc,
                                    tiempo_entrega_dias: (int) $det->tiempo_entrega_dias,
                                    lugar_recojo: $det->lugar_recojo ?? null,
                                    contenido_por_presentacion: (float) $det->contenido_por_presentacion,
                                    cantidad_requerida: (float) $det->cantidad,
                                    cantidad_requerida_base: (float) $det->cantidad_base,
                                    precio_unitario: (float) $det->precio_unitario,
                                    precio_unitario_base: (float) $det->precio_unitario_base,
                                    comentario: $det->comentario ?? null,
                                );

                                OrdenesCompraData::crear_logs(
                                    id_orden_compra_detalle: $id_oc_det,
                                    id_empleado: $id_empleado,
                                    estado: EstadoOrdenCompraDetalleLog::Pendiente,
                                );
                            }
                        }
                    }
                }

                // 7. Devolver el comparativo recién creado con el mismo formato del listado
                return self::listar(id_comparativo: $id_comparativo);
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Error al registrar el comparativo: ' . $e->getMessage());
        }
    }

    /**
     * Aprobar parcialmente una cotización y generar la Orden de Compra
     *
     * @param array $detalles_aprobados IDs de cotizacion_detalle aprobados
     */
    public static function aprobar_cotizacion_parcial(
        int $id_cotizacion,
        int $id_empresa_compradora,
        int $id_empleado,
        array $detalles_aprobados,
        ?float $tipo_cambio_aplicado = null
    ): array {
        try {
            return DB::transaction(function () use ($id_cotizacion, $id_empresa_compradora, $id_empleado, $detalles_aprobados, $tipo_cambio_aplicado) {

                // 1. Marcar cotización como Aprobada
                CotizacionesData::actualizar_estado($id_cotizacion, EstadoCotizacion::Aprobada);

                // 2. Marcar detalles como Aprobados / Rechazados
                CotizacionesData::actualizar_estados_aprobacion($id_cotizacion, $detalles_aprobados);

                // 3. Datos de la cotización aprobada
                $cotizacion = CotizacionesData::get_cotizaciones(id_cotizacion: $id_cotizacion);

                // 4. Calcular totales sobre los ítems aprobados (netos), más flete y otros gastos
                $detalles_cot = CotizacionesData::get_detalles_cotizacion(ids_cotizaciones: $id_cotizacion);
                $detalles_aprobados_data = array_filter(
                    is_array($detalles_cot) ? $detalles_cot : iterator_to_array($detalles_cot),
                    fn($d) => in_array($d->id_cotizacion_detalle, $detalles_aprobados)
                );

                $subtotal = array_sum(array_map(
                    fn($d) => (float) $d->cantidad * (float) $d->precio_unitario,
                    $detalles_aprobados_data
                ));

                $costo_flete = (float) ($cotizacion->costo_flete ?? 0);
                $otros_gastos = (float) ($cotizacion->otros_gastos ?? 0);
                $base = $subtotal + $costo_flete + $otros_gastos;

                $incluye_igv = (bool) $cotizacion->incluye_igv;
                $pct_igv = (float) $cotizacion->porcentaje_igv;

                if ($incluye_igv) {
                    $factor = 1 + ($pct_igv / 100);
                    $total_antes = round($base / $factor, 2);
                    $monto_igv = round($base - $total_antes, 2);
                    $total_despues = round($base, 2);
                } else {
                    $total_antes = round($base, 2);
                    $monto_igv = round($base * ($pct_igv / 100), 2);
                    $total_despues = round($base + $monto_igv, 2);
                }

                // 5. Crear la Orden de Compra
                $correlativoData = OrdenesCompraData::get_nuevo_correlativo();

                $id_orden = OrdenesCompraData::crear_orden(
                    id_cotizacion: $id_cotizacion,
                    id_empresa: $id_empresa_compradora,
                    id_proveedor: (int) $cotizacion->id_proveedor,
                    correlativo: $correlativoData['correlativo'],
                    numero_correlativo: (int) $correlativoData['numero_correlativo'],
                    fecha_hora_orden: now()->toDateTimeString(),
                    moneda: $cotizacion->moneda,
                    tipo_cambio_aplicado: $cotizacion->moneda !== 'Soles' ? $tipo_cambio_aplicado : 1,
                    es_auditable: $cotizacion->es_auditable ? 1 : 0,
                    metodo_pago: $cotizacion->metodo_pago,
                    incluye_igv: (bool) $cotizacion->incluye_igv,
                    porcentaje_igv: (float) $cotizacion->porcentaje_igv,
                    monto_igv: $monto_igv,
                    costo_flete: $costo_flete,
                    otros_gastos: $otros_gastos,
                    total_antes_igv: $total_antes,
                    total_despues_igv: $total_despues,
                    fecha_vencimiento_pago: $cotizacion->fecha_vencimiento_pago ?? null,
                );

                // 6. Crear detalles de OC (copiando los campos de despacho/almacén del detalle de cotización)
                foreach ($detalles_aprobados_data as $det) {
                    $tipo_despacho = TipoDespachoCompra::from($det->tipo_despacho);
                    $periodo = Periodo::from($det->tiempo_entrega_periodo);

                    $id_oc_det = OrdenesCompraData::crear_detalle_orden(
                        id_orden_compra: $id_orden,
                        id_cotizacion_detalle: $det->id_cotizacion_detalle,
                        id_producto: (int) $det->id_producto,
                        id_unidad_medida: (int) $det->id_unidad_medida_ctz,
                        id_almacen_recepcionista: (int) $det->id_almacen_recepcionista,
                        tipo_despacho: $tipo_despacho,
                        tiempo_entrega: (int) $det->tiempo_entrega,
                        tiempo_entrega_periodo: $periodo,
                        tiempo_entrega_dias: (int) $det->tiempo_entrega_dias,
                        lugar_recojo: $det->lugar_recojo ?? null,
                        contenido_por_presentacion: (float) $det->contenido_por_presentacion,
                        cantidad_requerida: (float) $det->cantidad,
                        cantidad_requerida_base: (float) $det->cantidad_base,
                        precio_unitario: (float) $det->precio_unitario,
                        precio_unitario_base: (float) $det->precio_unitario_base,
                        comentario: $det->comentario ?? null,
                    );

                    OrdenesCompraData::crear_logs(
                        id_orden_compra_detalle: $id_oc_det,
                        id_empleado: $id_empleado,
                        estado: EstadoOrdenCompraDetalleLog::Pendiente,
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
     * Listar comparativos con sus cotizaciones y detalles agrupados.
     * Si se pasa id_comparativo, retorna solo ese comparativo (útil desde registrar).
     */
    public static function listar(
        ?int $mes = null,
        ?int $year = null,
        ?int $id_comparativo = null
    ): array {
        // 1. Comparativos (por período o por ID específico)
        if ($id_comparativo) {
            $comp = ComparativoData::get_comparativos(id_comparativo: $id_comparativo);
            $comparativos = $comp ? [$comp] : [];
        } else {
            $comparativos = ComparativoData::get_comparativos(mes: $mes, yearcito: $year);
        }

        if (empty($comparativos))
            return ApiResponse::success([]);

        $ids_comparativos = array_map(fn($c) => $c->id_comparativo, $comparativos);

        // 2. Todas las cotizaciones de esos comparativos
        $cotizaciones = CotizacionesData::get_cotizaciones(ids_comparativos: $ids_comparativos);
        $ids_cotizaciones = array_map(fn($c) => $c->id_cotizacion, is_array($cotizaciones) ? $cotizaciones : iterator_to_array($cotizaciones));

        // 3. Todos los detalles y empresas en una sola consulta cada uno
        $detalles = CotizacionesData::get_detalles_cotizacion(ids_cotizaciones: $ids_cotizaciones);
        $empresas = CotizacionesData::get_empresas_cotizacion($ids_cotizaciones);

        // 4. Indexar para agrupar eficientemente
        $detalles_por_cot = [];
        foreach ($detalles as $d) {
            $detalles_por_cot[$d->id_cotizacion][] = $d;
        }

        $empresas_por_cot = [];
        foreach ($empresas as $e) {
            $empresas_por_cot[$e->id_cotizacion][] = $e;
        }

        $cots_por_comp = [];
        foreach ($cotizaciones as $c) {
            $cots_por_comp[$c->id_comparativo][] = array_merge(
                (array) $c,
                [
                    'detalles' => $detalles_por_cot[$c->id_cotizacion] ?? [],
                    'empresas' => $empresas_por_cot[$c->id_cotizacion] ?? [],
                ]
            );
        }

        // 5. Ensamblar el resultado final
        $resultado = array_map(fn($comp) => array_merge(
            (array) $comp,
            ['cotizaciones' => $cots_por_comp[$comp->id_comparativo] ?? []]
        ), $comparativos);

        return ApiResponse::success($resultado);
    }
}
