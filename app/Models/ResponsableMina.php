<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;

class ResponsableMina extends Model
{
    protected $table = 'responsable_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'id_usuario',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function asignar_responsable(int $id_mina, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        return self::insertGetId([
            'id_mina' => $id_mina,
            'id_usuario' => $id_usuario,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function cerrar_responsable_activo(int $id_mina, string $fecha_cierre)
    {
        return self::where('id_mina', $id_mina)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    public static function get_responsables_historial(int $id_mina)
    {
        $sql = '
        SELECT
            rm.id AS id_asignacion,
            rm.id_usuario,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            rm.fecha_inicio,
            rm.fecha_fin,
            rm.estado
        FROM
            responsable_mina rm
        INNER JOIN usuario u ON u.id = rm.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            rm.id_mina = :id_mina
        ORDER BY rm.fecha_inicio DESC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, ['id_mina' => $id_mina]);
    }
}
