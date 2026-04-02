<?php

namespace App\Models;

use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla que presenta las reposiciones que realiza logistica
 * a los almacenes que fueron prestamistas, con el fin
 * de reponer el stock entregado.
 */
class PrestamoAlmacenReposicion extends Model
{
    protected $table = 'prestamo_almacen_reposicion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen', // el prestamo que se esta reponiendo
        'id_almacen_entrega', // uno de los almacenes principales
        'id_empleado_registro', // empleado que realiza/registra la reposicion
        'correlativo', // prefijo: RPS
        'numero_correlativo',
        'observacion',
        'fecha_hora_reposicion', // fecha y hora que el usuario fija en la ui
        'evidencias',
        'created_at', // fecha y hora de registro en el sistema
        'estado', // En Despacho / Recepcionado
    ];

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_reposicion',
            prefijo: 'RPS',
            filtros: ['id_almacen_entrega' => $id_almacen],
            columnaFecha: 'fecha_hora_reposicion'
        );
    }

    /**
     * Obtener una reposicion o el historial de reposiciones de un préstamo
     */
    public static function get_reposiciones(
        ?int $id_reposicion = null,
        ?int $id_prestamo_almacen = null
    ) {
        $sql = '
        SELECT 
            r.id as id_reposicion,
            r.id_almacen_entrega,
            r.id_prestamo_almacen,
            a.nombre AS almacen_entrega,
            r.correlativo,
            r.fecha_hora_reposicion,
            r.observacion,
            r.evidencias,
            CONCAT(e.nombre, " ", e.apellido) AS registrado_por,
            r.created_at,
            r.estado
        FROM 
            prestamo_almacen_reposicion r
        INNER JOIN almacen a ON a.id = r.id_almacen_entrega
        INNER JOIN empleado e ON e.id = r.id_empleado_registro
        WHERE 
            r.id_prestamo_almacen = :id_prestamo_almacen
        ';

        $params = [];

        if ($id_reposicion) {
            $sql .= ' AND r.id = :id_reposicion';
            $params['id_reposicion'] = $id_reposicion;
            return DB::selectOne($sql, $params);
        }

        if ($id_prestamo_almacen) {
            $sql .= ' AND r.id_prestamo_almacen = :id_prestamo_almacen';
            $params['id_prestamo_almacen'] = $id_prestamo_almacen;
        }

        $sql .= ' ORDER BY r.fecha_hora_reposicion DESC;';
        return DB::select($sql, $params);
    }
}
