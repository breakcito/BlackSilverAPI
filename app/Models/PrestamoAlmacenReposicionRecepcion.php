<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenReposicionRecepcion extends Model
{
    protected $table = 'prestamo_almacen_reposicion_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion',
        'id_empleado_registro', // quien recibe
        'observacion',
        'fecha_hora_recepcion',
        'evidencias', // [{"url": "", "path_relativo": "", "nombre_original": "", "extension": ""}, ...]
        'con_incidencia', // 1 | 0
        'created_at', // automatico
        'estado', // Recepcionado Parcialmente | Recepcionado
    ];

    /**
     * Crea una cabecera de recepción de reposición.
     */
    public static function crear_recepcion(
        int $id_reposicion,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias = null,
        bool $con_incidencia = false,
        string $estado = 'Recepcionado'
    ): int {
        return self::insertGetId([
            'id_prestamo_almacen_reposicion' => $id_reposicion,
            'id_empleado_registro' => $id_empleado,
            'fecha_hora_recepcion' => $fecha_hora_recepcion,
            'observacion' => $observacion ?? '',
            'evidencias' => $evidencias,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'estado' => $estado,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener el historial de recepciones de una reposición.
     */
    public static function get_recepciones(int $id_reposicion)
    {
        $sql = "
            SELECT
                pr.id as id_recepcion,
                pr.fecha_hora_recepcion,
                pr.observacion,
                pr.evidencias,
                pr.con_incidencia,
                pr.estado,
                pr.created_at,
                CONCAT(e.nombre, ' ', e.apellido) as empleado_registro
            FROM
                prestamo_almacen_reposicion_recepcion pr
            INNER JOIN empleado e ON e.id = pr.id_empleado_registro
            WHERE
                pr.id_prestamo_almacen_reposicion = :id_reposicion
            ORDER BY pr.fecha_hora_recepcion DESC
        ";

        return DB::select($sql, ['id_reposicion' => $id_reposicion]);
    }
}
