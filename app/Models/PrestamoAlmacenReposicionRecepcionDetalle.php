<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenReposicionRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion_recepcion',
        'id_prestamo_almacen_reposicion_detalle',
        'cantidad_recepcionada_base',
        'estado', // Recepcionado parcialmente | Recepcionado
    ];

    /**
     * Crea un detalle de recepción de reposición.
     */
    public static function crear_detalle(
        int $id_recepcion,
        int $id_reposicion_detalle,
        float $cantidad_recep_base,
        string $estado = 'Recepcionado'
    ): bool {
        return self::insert([
            'id_prestamo_almacen_reposicion_recepcion' => $id_recepcion,
            'id_prestamo_almacen_reposicion_detalle' => $id_reposicion_detalle,
            'cantidad_recepcionada_base' => $cantidad_recep_base,
            'estado' => $estado,
        ]);
    }

    /**
     * Obtener los detalles de una recepción de reposición.
     */
    public static function get_detalles(int $id_recepcion)
    {
        $sql = "
            SELECT
                prd.id as id_recepcion_detalle,
                prd.id_prestamo_almacen_reposicion_detalle,
                prd.cantidad_recepcionada_base,
                prd.estado,
                p.nombre as producto,
                um.abreviatura as unidad_medida_base_abv
            FROM
                prestamo_almacen_reposicion_recepcion_detalle prd
            INNER JOIN prestamo_almacen_reposicion_detalle rd ON rd.id = prd.id_prestamo_almacen_reposicion_detalle
            INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
            INNER JOIN producto p ON p.id = pd.id_producto
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE
                prd.id_prestamo_almacen_reposicion_recepcion = :id_recepcion
        ";

        return DB::select($sql, ['id_recepcion' => $id_recepcion]);
    }
}
