<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que representa a la tabla utilizada para registrar la RECEPCION de 
 * una transferencua hecha tras hacer la recepcion de una orden de compra a un
 * almacen al que no estaba inicialmente destinado
 */
class OrdenCompraTransferenciaRecepcion extends Model
{
    protected $table = 'orden_compra_transferencia_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_transferencia', // de que transferencia se esta recepcionando
        'id_empleado_registro', // el empleado que recepciona/registra
        //
        'numero_correlativo', // por transferencia
        // 
        'observacion',
        'fecha_hora_recepcion',
        'evidencias',
        'con_incidencia',
        //
        'created_at',
        'estado', // Recepcionado / Recepcionado parcialmente
    ];

    public static function get_recepciones(
        ?int $id_recepcion = null,
        ?int $id_transferencia = null
    ) {
        $sql = '
        SELECT
            r.id as id_recepcion,
            r.id_orden_compra_transferencia,
            -- 
            CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
            -- 
            r.observacion,
            r.fecha_hora_recepcion,
            r.evidencias,
            r.con_incidencia,
            -- 
            r.created_at,
            r.estado
        FROM
            orden_compra_transferencia_recepcion r
        INNER JOIN empleado e ON
            e.id = r.id_empleado_registro
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_recepcion !== null) {
            $sql .= " AND r.id = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
            return DB::selectOne($sql, $params);
        }

        if ($id_transferencia !== null) {
            $sql .= " AND r.id_orden_compra_transferencia = :id_transferencia";
            $params['id_transferencia'] = $id_transferencia;
        }

        $sql .= " ORDER BY r.created_at DESC";

        return DB::select($sql, $params);
    }
}
