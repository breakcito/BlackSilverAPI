<?php

namespace App\Modules\PrestamosAlmacen\Models;

use App\Shared\Enums\EstadoPrestamo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacen extends Model
{
    protected $table = 'prestamo_almacen';

    public static function get_prestamos(int $id_almacen_solicitante, ?string $estado = null)
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
            $sql .= " AND pa.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY pa.created_at DESC";

        return DB::select($sql, $params);
    }

    public static function crear_prestamo(
        int $id_almacen_solicitante,
        int $id_usuario_solicitante,
        string $correlativo,
        int $numero_correlativo,
        ?string $motivo,
        string $fecha_prestamo
    ) {
        return DB::table('prestamo_almacen')->insertGetId([
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_usuario_solicitante' => $id_usuario_solicitante,
            'correlativo'           => $correlativo,
            'numero_correlativo'    => $numero_correlativo,
            'motivo'                => $motivo,
            'fecha_prestamo'        => $fecha_prestamo,
            'created_at'            => now(),
            'estado'                => EstadoPrestamo::Generado->value
        ]);
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

        if (!$cabecera) {
            return null;
        }

        $cabecera->detalles = PrestamoAlmacenDetalle::get_detalles_by_prestamo($id);

        return $cabecera;
    }
}
