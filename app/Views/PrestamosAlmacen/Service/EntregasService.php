<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenReposicion;
use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\EntregasData;
use App\Views\PrestamosAlmacen\Data\EntregasDetalleData;
use App\Views\PrestamosAlmacen\Data\InventarioData;
use App\Views\PrestamosAlmacen\Data\ReposicionesData;
use Illuminate\Support\Facades\DB;

class EntregasService
{
    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_historial_entregas(int $id_prestamo): array
    {
        $data = EntregasData::get_entregas_por_prestamo($id_prestamo);
        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(id_entrega: (int) $entrega->id_entrega);
        }
        return ApiResponse::success($data);
    }

    /**
     * Registra la recepción de una o más reposiciones.
     * 
     * $recepciones: [
     *    'id_reposicion' => int,
     *    'items' => [
     *        'id_solicitud_reabastecimiento_detalle' => int,
     *        'es_nuevo_lote' => bool,
     *        'cantidad_base' => float,
     *        'id_lote_existente' => ?int,
     *        'fecha_vencimiento' => ?string (Y-m-d),
     *        'id_unidad_medida' => ?int,
     *        'contenido_por_presentacion' => ?float,
     *        'fecha_ingreso' => ?string (Y-m-d H:i:s),
     *        'descripcion' => ?string
     *    ]
     * ]
     */
    public static function recibir_reposiciones(array $recepciones): array
    {
        try {
            DB::beginTransaction();

            foreach ($recepciones as $recepcion) {
                $id_reposicion = (int) $recepcion['id_reposicion'];
                $items = $recepcion['items'];

                self::procesar_recepcion_individual($id_reposicion, $items);
            }

            DB::commit();
            return ApiResponse::success(null, 'Reposiciones recibidas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al procesar recepciones: ' . $e->getMessage());
        }
    }

    /**
     * Procesa la recepción de una reposición específica.
     * 
     * $items: [
     *    'id_solicitud_reabastecimiento_detalle' => int,
     *    'es_nuevo_lote' => bool,
     *    'cantidad_base' => float,
     *    // ... resto de campos de item (ver recibir_reposiciones)
     * ]
     */
    private static function procesar_recepcion_individual(int $id_reposicion, array $items): void
    {
        $reposicion = PrestamoAlmacenReposicion::find($id_reposicion);
        if (!$reposicion) {
            throw new \Exception("La reposición ID {$id_reposicion} no existe");
        }

        $prestamo = PrestamoAlmacen::find($reposicion->id_prestamo_almacen);
        if (!$prestamo) {
            throw new \Exception("El préstamo asociado a la reposición {$reposicion->correlativo} no existe");
        }

        // El almacén de destino es el que prestó el producto originalmente
        $id_almacen_destino = (int) $prestamo->id_almacen_prestamista;
        if (!$id_almacen_destino) {
            throw new \Exception("No se pudo determinar el almacén de destino para la reposición {$reposicion->correlativo}");
        }

        $detalles_db = ReposicionesData::get_detalles_entrega_reposicion($id_reposicion);
        $detalles_grouped = collect($detalles_db)->groupBy('id_solicitud_reabastecimiento_detalle');

        foreach ($items as $item) {
            self::procesar_item_recepcion(
                $item,
                $detalles_grouped,
                $id_reposicion,
                $reposicion->correlativo,
                $prestamo->correlativo,
                $id_almacen_destino
            );
        }

        // Verificar si la reposición está completa para cerrarla
        ReposicionesData::verificar_y_completar_reposicion($id_reposicion);
    }

    /**
     * Procesa un ítem individual de una reposición.
     * 
     * $item: [
     *    'id_solicitud_reabastecimiento_detalle' => int,
     *    'es_nuevo_lote' => bool,
     *    'cantidad_base' => float,
     *    'id_lote_existente' => ?int,
     *    // ... otros campos opcionales
     * ]
     */
    private static function procesar_item_recepcion(
        array $item,
        \Illuminate\Support\Collection $detalles_grouped,
        int $id_reposicion,
        string $correlativo_reposicion,
        string $correlativo_prestamo,
        int $id_almacen_destino
    ): void {
        $id_prestamo_detalle = (int) $item['id_solicitud_reabastecimiento_detalle'];
        $es_nuevo_lote = (bool) $item['es_nuevo_lote'];
        $cantidad_base = (float) $item['cantidad_base'];

        if (!$detalles_grouped->has($id_prestamo_detalle)) {
            throw new \Exception("El detalle de préstamo {$id_prestamo_detalle} no forma parte de la reposición {$correlativo_reposicion}");
        }

        $db_detalles = $detalles_grouped->get($id_prestamo_detalle);
        $detalle_base = $db_detalles->first();
        $id_producto = (int) $detalle_base->id_producto;

        $descripcion_kardex = "Recepción de reposición {$correlativo_reposicion} del préstamo {$correlativo_prestamo}";

        $id_lote_final = null;
        $cantidad_movimiento_lote = 0;

        if ($es_nuevo_lote) {
            $id_lote_final = self::registrar_lote_nuevo($item, $id_producto, $id_almacen_destino, $detalle_base, $cantidad_base);

            // Para lotes nuevos, el movimiento en unidades de lote es la cantidad ingresada / contenido_por_presentacion
            $contenido = !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalle_base->contenido_por_presentacion_solicitado;
            $cantidad_movimiento_lote = $cantidad_base / ($contenido ?: 1);
        } else {
            $id_lote_existente = (int) $item['id_lote_existente'];
            $resultado = InventarioData::registrar_recepcion_lote_existente($id_lote_existente, $cantidad_base);
            $id_lote_final = $id_lote_existente;
            $cantidad_movimiento_lote = $resultado['cantidad_lote_ingresada'];
        }

        // Registrar en Kardex
        InventarioData::registrar_kardex_recepcion(
            $id_lote_final,
            $id_reposicion,
            $cantidad_movimiento_lote,
            $cantidad_base,
            $descripcion_kardex
        );

        // Marcar detalles como recibidos
        foreach ($db_detalles as $db_d) {
            ReposicionesData::marcar_como_recibido((int) $db_d->id_entrega_detalle, $id_lote_final);
        }
    }

    /**
     * Encapsula la creación de un lote nuevo durante la recepción.
     * 
     * $item: [
     *    'fecha_vencimiento' => ?string,
     *    'fecha_ingreso' => ?string,
     *    'id_unidad_medida' => ?int,
     *    'contenido_por_presentacion' => ?float,
     *    'descripcion' => ?string
     * ]
     */
    private static function registrar_lote_nuevo(array $item, int $id_producto, int $id_almacen, $detalle_base, float $cantidad_base): int
    {
        $fecha_vencimiento = !empty($item['fecha_vencimiento']) ? date('Y-m-d', strtotime($item['fecha_vencimiento'])) : null;
        $fecha_ingreso = !empty($item['fecha_ingreso']) ? date('Y-m-d H:i:s', strtotime($item['fecha_ingreso'])) : null;
        $id_unidad_medida = !empty($item['id_unidad_medida']) ? (int)$item['id_unidad_medida'] : (int) $detalle_base->id_unidad_medida_solicitada;
        $contenido = !empty($item['contenido_por_presentacion']) ? (float)$item['contenido_por_presentacion'] : (float) $detalle_base->contenido_por_presentacion_solicitado;

        $cantidad_en_unidad = $cantidad_base / ($contenido ?: 1);

        return InventarioData::registrar_recepcion_lote_nuevo(
            $id_producto,
            $id_unidad_medida,
            $id_almacen,
            $fecha_vencimiento,
            $cantidad_en_unidad,
            $cantidad_base,
            $contenido,
            $item['descripcion'] ?? null,
            $fecha_ingreso
        );
    }
}
