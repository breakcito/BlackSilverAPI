<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResponsableAlmacen extends Model
{
    protected $table = 'responsable_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',
        'id_usuario',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function asignar_responsable(int $id_almacen, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        return self::insertGetId([
            'id_almacen' => $id_almacen,
            'id_usuario' => $id_usuario,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);
    }

    public static function cerrar_responsable_activo(int $id_almacen, string $fecha_cierre)
    {
        return self::where('id_almacen', $id_almacen)
            ->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado' => \App\Shared\Enums\EstadoBase::Inactivo->value,
            ]);
    }

    public static function get_responsables_historial(int $id_almacen)
    {
        $sql = '
        SELECT
            ra.id AS id_asignacion,
            ra.id_usuario,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN usuario u ON u.id = ra.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            ra.id_almacen = :id_almacen
        ORDER BY ra.fecha_inicio DESC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
