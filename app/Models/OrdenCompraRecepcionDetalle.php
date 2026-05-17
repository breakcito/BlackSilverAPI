<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcionDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraRecepcionDetalle extends Model
{
    protected $table = 'orden_compra_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_recepcion', // la recepcion
        'id_orden_compra_detalle', // el detalle de la compra
        'id_lote_producto', // el lote del que se ajusto el stock o que se genero como nuevo
        'id_activo_fijo', // el NUEVO activo fijo que se recepciona
        'es_ajuste_stock', // 1 si fue un ajuste de stock o 0 si fue un registro
        'cantidad_recepcionada', // segun la unidad de la orden de compra
        'cantidad_recepcionada_base', // segun la unidad base del producto
        'comentario', // alguna observacion del detalle recibido
        'estado', // Recepcionado parcialmente | Recepcionado
    ];

    /**
     * Crea uno o varios detalles de recepción de reposición en una sola consulta.
     * @param array $detalles:
     *  {
     *      id_orden_compra_detalle: int, 
     *      id_lote_producto: int, // el lote del que se ajusto el stock o que se genero como nuevo
     *      es_ajuste_stock: bool, // 1 si fue un ajuste de stock o 0 si fue un registro
     *      cantidad_recepcionada: int, 
     *      cantidad_recepcionada_base: int, 
     *      comentario: string | null,
     *      estado: EstadoOrdenCompraRecepcionDetalle
     *  }
     */
    public static function crear_detalle(
        int $id_recepcion,
        array $detalles
    ): bool {
        // Si pasas un solo registro asociativo, lo convertimos en un arreglo de arreglos.
        if (!isset($detalles[0]) || !is_array($detalles[0])) {
            $detalles = [$detalles];
        }

        $insertData = [];

        foreach ($detalles as $detalle) {
            $insertData[] = [
                'id_orden_compra_recepcion' => $id_recepcion,
                'id_orden_compra_detalle' => $detalle['id_orden_compra_detalle'],
                'id_lote_producto' => $detalle['id_lote_producto'],
                'es_ajuste_stock' => $detalle['es_ajuste_stock'] ? 1 : 0,
                'cantidad_recepcionada' => $detalle['cantidad_recepcionada'],
                'cantidad_recepcionada_base' => $detalle['cantidad_recepcionada_base'],
                'comentario' => $detalle['comentario'],
                'estado' => $detalle['estado']->value ?? EstadoOrdenCompraRecepcionDetalle::RecepcionCompleta->value,
            ];
        }

        // Ejecuta todo en una sola consulta a la base de datos
        return self::insert($insertData);
    }

    /**
     * Obtener los detalles filtrando dinámicamente por una o varias 
     * recepciones, o por los IDs propios del detalle.
     */
    public static function get_detalles(
        array|int|null $ids_recepciones = null,
        array|int|null $ids_detalles = null
    ) {
        $sql = "
        SELECT
            rcd.id AS id_recepcion_detalle,
            rcd.id_orden_compra_recepcion,
            rcd.id_orden_compra_detalle,
            -- 
            ocd.id_producto,
            prd.nombre AS producto,
            -- 
            ocd.id_almacen_recepcionista AS id_almacen_destino,
            alm.nombre AS almacen_destino,
            -- 
            umb.id AS id_unidad_medida_base,
            umb.abreviatura AS unidad_medida_base_abv,
            -- 
            umc.id AS id_unidad_medida_oc,
            umc.abreviatura AS unidad_medida_oc_abv,
            -- 
            rcd.cantidad_recepcionada,
            rcd.cantidad_recepcionada_base,
            -- 
            CASE
            	-- si el almacen que ha recepcionado los productos es diferente al 
                -- almacen que deberia haberlo hecho
            	WHEN ocd.id_almacen_recepcionista != ocr.id_almacen_recepcionista THEN (1)
                ELSE (0)
            END as es_para_otro_almacen,
            (
                SELECT
                	SUM(trnd.cantidad_transferida_base)
                FROM orden_compra_transferencia_detalle trnd
                WHERE 
                	trnd.id_orden_compra_recepcion_detalle = rcd.id
            ) as cantidad_transferida_base,
            -- 
            rcd.comentario,
            rcd.estado
        FROM
            orden_compra_recepcion_detalle rcd
        INNER JOIN orden_compra_recepcion ocr on ocr.id = rcd.id_orden_compra_recepcion
        INNER JOIN orden_compra_detalle ocd on ocd.id = rcd.id_orden_compra_detalle
        INNER JOIN producto prd on prd.id = ocd.id_producto
        INNER JOIN almacen alm on alm.id = ocd.id_almacen_recepcionista
        INNER JOIN unidad_medida umb on umb.id = prd.id_unidad_medida_base
        INNER JOIN unidad_medida umc on umc.id = ocd.id_unidad_medida
        WHERE 1=1
        ";

        $params = [];

        // Filtro para id_prestamo_almacen_reposicion_recepcion
        if ($ids_recepciones !== null) {
            $ids = (array) $ids_recepciones;
            $placeholders = [];

            foreach ($ids as $index => $id) {
                $paramName = "recep_{$index}";
                $placeholders[] = ":{$paramName}";
                $params[$paramName] = $id;
            }

            $sql .= " AND rcd.id_orden_compra_recepcion IN (" . implode(',', $placeholders) . ")";
        }

        // Filtro para el ID propio del detalle (prd.id)
        if ($ids_detalles !== null) {
            $ids = (array) $ids_detalles;
            $placeholders = [];

            foreach ($ids as $index => $id) {
                $paramName = "det_{$index}";
                $placeholders[] = ":{$paramName}";
                $params[$paramName] = $id;
            }

            $sql .= " AND rcd.id IN (" . implode(',', $placeholders) . ")";
        }

        return DB::select($sql, $params);
    }
}
