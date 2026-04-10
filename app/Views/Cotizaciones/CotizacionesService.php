<?php

namespace App\Views\Cotizaciones;

use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\Cotizacion\EstadoCotizacion;
use App\Views\Cotizaciones\Data\CotizacionesData;
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

        try {
            return DB::transaction(function () use ($productos, $cotizaciones) {
                $fecha_ahora = now()->toDateTimeString();
                $year_short  = date('y'); // 24, 25, 26...

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

                // 3. Pre-procesar estados de las cotizaciones
                // Si alguna viene como "Aprobada", las demás pasan a "Desestimada"
                $hay_aprobada = false;
                foreach ($cotizaciones as $c) {
                    if (($c['estado'] ?? '') === EstadoCotizacion::APROBADA->value) {
                        $hay_aprobada = true;
                        break;
                    }
                }

                // 4. Registrar cada Cotización
                foreach ($cotizaciones as $index => $c) {
                    $numero = CotizacionesData::get_siguiente_numero_correlativo();
                    $correlativo = "CTZ-{$year_short}-" . str_pad($numero, 5, '0', STR_PAD_LEFT);

                    // Determinar estado final
                    $estado_final = $c['estado'] ?? EstadoCotizacion::GENERADA->value;
                    if ($hay_aprobada && $estado_final !== EstadoCotizacion::APROBADA->value) {
                        $estado_final = EstadoCotizacion::DESESTIMADA->value;
                    }

                    $id_cotizacion = CotizacionesData::crear_cotizacion([
                        'id_comparativo'         => $id_comparativo,
                        'id_proveedor'           => (int)$c['id_proveedor'],
                        'moneda'                 => (string)$c['moneda'],
                        'correlativo'            => $correlativo,
                        'numero_correlativo'     => $numero,
                        'metodo_pago'            => (string)$c['metodo_pago'],
                        'fecha_vencimiento_pago' => $c['fecha_vencimiento_pago'] ?? null,
                        'total_antes_igv'        => (float)$c['total_antes_igv'],
                        'incluye_igv'            => (bool)$c['incluye_igv'],
                        'porcentaje_igv'         => (float)$c['porcentaje_igv'],
                        'monto_igv'              => (float)$c['monto_igv'],
                        'total_despues_igv'      => (float)$c['total_despues_igv'],
                        'observacion'            => $c['observacion'] ?? null,
                        'evidencia'              => $c['evidencia'] ?? null,
                        'fecha_hora_cotizacion'  => $fecha_ahora,
                        'estado'                 => $estado_final,
                        'created_at'             => $fecha_ahora,
                    ]);

                    // 5. Registrar Detalles de la Cotización
                    foreach ($c['detalles'] as $det) {
                        $id_comp_det = $mapa_productos[$det['id_producto']] ?? null;
                        
                        if ($id_comp_det) {
                            CotizacionesData::crear_cotizacion_detalle([
                                'id_cotizacion'              => $id_cotizacion,
                                'id_comparativo_detalle'     => $id_comp_det,
                                'id_unidad_medida'           => (int)$det['id_unidad_medida'],
                                'cantidad'                   => (float)$det['cantidad'],
                                'contenido_por_presentacion' => (float)$det['contenido_por_presentacion'],
                                'cantidad_base'              => (float)$det['cantidad_base'],
                                'precio_unitario'            => (float)$det['precio_unitario'],
                                'precio_unitario_base'       => (float)$det['precio_unitario_base'],
                                'comentario'                 => $det['comentario'] ?? null,
                            ]);
                        }
                    }
                }

                return ApiResponse::success(null, 'Comparativo y cotizaciones registrados correctamente.');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Error al registrar el comparativo: ' . $e->getMessage());
        }
    }

    /**
     * Listar cotizaciones agrupadas
     */
    public static function listar(): array
    {
        $result = CotizacionesData::get_listado_agrupado();
        return ApiResponse::success($result);
    }
}
