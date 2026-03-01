<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacen extends Model
{
    protected $table = 'prestamo_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_almacen_prestamista',
        'id_empleado_registro',
        //
        'correlativo',
        'numero_correlativo',
        'fecha_hora_prestamo',
        //
        'created_at',
        'estado',
    ];

    public static function get_prestamos(int $id_almacen_solicitante, ?string $estado = null, ?string $mes = null, ?string $anio = null)
    {
        $sql = "
        SELECT
            pa.id AS id_prestamo,
            pa.id_almacen_solicitante,
            a.nombre AS almacen_solicitante,
            pa.id_usuario_solicitante,
            CONCAT(e.nombre, ' ', e.apellido) AS solicitante,
            CONCAT(pa.correlativo, '-', DATE_FORMAT(pa.created_at, '%y'), '-', LPAD(pa.numero_correlativo, 5, '0')) AS codigo_prestamo,
            pa.motivo,
            pa.fecha_prestamo,
            pa.created_at,
            pa.estado
        FROM
            prestamo_almacen pa
        INNER JOIN almacen a ON a.id = pa.id_almacen_solicitante
        INNER JOIN usuario u ON u.id = pa.id_usuario_solicitante
        INNER JOIN empleado e ON e.id = u.id_empleado
        WHERE
            pa.id_almacen_solicitante = :id_almacen
        ";

        $params = ['id_almacen' => $id_almacen_solicitante];

        if ($estado) {
            $sql .= ' AND pa.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($mes && $anio) {
            $sql .= ' AND MONTH(pa.created_at) = :mes AND YEAR(pa.created_at) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($mes) {
            $sql .= ' AND MONTH(pa.created_at) = :mes';
            $params['mes'] = $mes;
        } elseif ($anio) {
            $sql .= ' AND YEAR(pa.created_at) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY pa.created_at DESC';

        return DB::select($sql, $params);
    }

    public static function get_prestamo_by_id(int $id)
    {
        $sql = "
        SELECT
            pa.id AS id_prestamo,
            pa.id_almacen_solicitante,
            a.nombre AS almacen_solicitante,
            pa.id_usuario_solicitante,
            CONCAT(e.nombre, ' ', e.apellido) AS solicitante,
            CONCAT(pa.correlativo, '-', DATE_FORMAT(pa.created_at, '%y'), '-', LPAD(pa.numero_correlativo, 5, '0')) AS codigo_prestamo,
            pa.motivo,
            pa.fecha_prestamo,
            pa.created_at,
            pa.estado
        FROM
            prestamo_almacen pa
        INNER JOIN almacen a ON a.id = pa.id_almacen_solicitante
        INNER JOIN usuario u ON u.id = pa.id_usuario_solicitante
        INNER JOIN empleado e ON e.id = u.id_empleado
        WHERE
            pa.id = :id
        ";

        $cabecera = DB::selectOne($sql, ['id' => $id]);

        if (! $cabecera) {
            return null;
        }

        $cabecera->detalles = PrestamoAlmacenDetalle::get_detalles_by_prestamo($id);

        return $cabecera;
    }
}
