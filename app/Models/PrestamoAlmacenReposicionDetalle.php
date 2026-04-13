<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoDetalleReposicion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla que presenta CADA DETALLE/PRODUCTO de las reposiciones que
 * realiza logistica a los almacenes que fueron prestamistas,
 * con el fin de reponer el stock entregado.
 */
class PrestamoAlmacenReposicionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion', // la reposicion
        'id_prestamo_almacen_detalle', // el detalle del prestamo que se esta reponiendo
        'id_lote_producto', // el lote del almacen principal elegido para reponer
        'cantidad_base', // cuanto representa segun la unidad de medida base del producto
        'cantidad_lote', // cuanto representa para el lote usado para la entrega
        'cantidad_prestamo', // cuanto representa para la unidad de medida del prestamo
        'estado', // En Despacho / Recepcionado
    ];

    /**
     * Metodo helper que ayuda a registrar un detalle de una reposicion
     */
    public static function crear_detalle(
        int $id_reposicion,
        int $id_prestamo_detalle,
        int $id_lote_producto,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_prestamo,
    ): int {
        return self::insertGetId([
            'id_prestamo_almacen_reposicion' => $id_reposicion,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_producto' => $id_lote_producto,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_prestamo' => $cantidad_prestamo,
            'estado' => EstadoDetalleReposicion::EnDespacho->value,
        ]);
    }


    /**
     * Obtiene uno o todos los detalles de una reposición.
     */
    public static function get_detalles(?int $id_reposicion = null, ?int $id_detalle = null): array
    {
        $sql = '
        SELECT 
            rd.id as id_reposicion_detalle,
            rd.id_prestamo_almacen_detalle,
            --
            p.id as id_producto,
            p.nombre AS producto,
            p.es_perecible,
            --
            -- unidad de medida del producto (base)
            p.id_unidad_medida_base,
            um_bs.nombre as unidad_medida_base,
            um_bs.abreviatura AS unidad_medida_base_abv,
            rd.cantidad_base,
            --
            -- lote de salida
            rd.id_lote_producto,
            lt.correlativo AS lote_correlativo,
            --
            -- unidad de medida del lote usado para la entrega de reposicion
            um_lt.id as id_unidad_medida_lote,
            um_lt.nombre as unidad_medida_lote,
            um_lt.abreviatura as unidad_medida_lote_abv,
            rd.cantidad_lote,
            --
            -- unidad de medida del prestamo (solicitada)
            pd.id_unidad_medida as id_unidad_medida_pr,
            rd.cantidad_prestamo,
            --
            rd.estado
        FROM 
            prestamo_almacen_reposicion_detalle rd
        INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
        INNER JOIN producto p ON p.id = pd.id_producto
        INNER JOIN unidad_medida um_bs ON um_bs.id = p.id_unidad_medida_base
        INNER JOIN lote_producto lt ON lt.id = rd.id_lote_producto
        INNER JOIN unidad_medida um_lt ON um_lt.id = lt.id_unidad_medida
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_detalle !== null) {
            $sql .= ' AND rd.id = :id_detalle';
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_reposicion !== null) {
            $sql .= ' AND rd.id_prestamo_almacen_reposicion = :id_reposicion';
            $params['id_reposicion'] = $id_reposicion;
        }

        $sql .= ' ORDER BY p.nombre ASC';

        return DB::select($sql, $params);
    }
}
