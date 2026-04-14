<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicionDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenReposicionRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion_recepcion',
        'id_prestamo_almacen_reposicion_detalle',
        'id_lote_producto', // El id del posible lote generado 
        'tipo_movimiento', // Nuevo Lote o Ajuste de Stock
        'cantidad_recepcionada_base',
        'estado', // Recepcionado parcialmente | Recepcionado
    ];

    /**
     * Crea uno o varios detalles de recepción de reposición en una sola consulta.
     * @param array $detalles:
     *  {
     *      id_reposicion_detalle: int, 
     *      cantidad_recepcionada_base: int, 
     *      id_lote_producto: int | null,
     *      tipo_movimiento: string, // Nuevo Lote o Ajuste de Stock
     *      estado: EstadoPrestamoReposicionDetalle
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
                'id_prestamo_almacen_reposicion_recepcion' => $id_recepcion,
                'id_prestamo_almacen_reposicion_detalle' => $detalle['id_reposicion_detalle'],
                'id_lote_producto' => $detalle['id_lote_producto'] ?? null,
                'tipo_movimiento' => $detalle['tipo_movimiento'] ?? null,
                'cantidad_recepcionada_base' => $detalle['cantidad_recepcionada_base'],
                'estado' => $detalle['estado']->value ?? EstadoPrestamoReposicionDetalle::RecepcionCompleta->value,
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
        SELECT DISTINCT
            prd.id AS id_recepcion_detalle,
            prd.id_prestamo_almacen_reposicion_recepcion AS id_recepcion,
            prd.id_prestamo_almacen_reposicion_detalle AS id_reposicion_detalle,
            -- 
            p.nombre AS producto,
            prdt.nombre as producto_destino,
            -- 
            um.id AS id_unidad_medida_base,
            um.abreviatura AS unidad_medida_base_abv,
            -- 
            prd.cantidad_recepcionada_base,
            -- 
            -- Nuevo Lote / Ajuste de Stock
            prd.tipo_movimiento,
            -- 
            -- Si genero un nuevo lote
            prd.id_lote_producto,
            lot.correlativo AS lote_correlativo,
            -- 
            prd.estado
        FROM
            prestamo_almacen_reposicion_recepcion_detalle prd
        INNER JOIN prestamo_almacen_reposicion_detalle rd ON
            rd.id = prd.id_prestamo_almacen_reposicion_detalle
        INNER JOIN prestamo_almacen_detalle pd ON
            pd.id = rd.id_prestamo_almacen_detalle
        INNER JOIN producto p ON
            p.id = pd.id_producto
        INNER JOIN unidad_medida um ON
            um.id = p.id_unidad_medida_base
        -- 
        -- jon left por si se genero un nuevo lote
        LEFT JOIN lote_producto lot ON
            lot.id = prd.id_lote_producto
        -- 
        -- joins para saber para que sera usado lo recepcionado
        LEFT JOIN solicitud_reabastecimiento_detalle srd ON
            srd.id = pd.id_solicitud_reabastecimiento_detalle
        LEFT JOIN requerimiento_almacen_detalle rad ON
            rad.id = srd.id_requerimiento_almacen_detalle
        LEFT JOIN producto prdt ON
            prdt.id = rad.id_producto_destino
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

            $sql .= " AND prd.id_prestamo_almacen_reposicion_recepcion IN (" . implode(',', $placeholders) . ")";
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

            $sql .= " AND prd.id IN (" . implode(',', $placeholders) . ")";
        }

        return DB::select($sql, $params);
    }
}
