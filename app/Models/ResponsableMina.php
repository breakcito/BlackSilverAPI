<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResponsableMina extends Model
{
    protected $table = 'responsable_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'id_empleado',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function check_usuario_autorizado_mina(int $id_usuario, int $id_mina, int $id_concesion)
    {

        $sql = '
        SELECT EXISTS(
            SELECT 1
            FROM usuario_empresa ue
            INNER JOIN empresa_mina em ON em.id_empresa = ue.id_empresa
            INNER JOIN contrato_concesion cc ON cc.id_empresa = ue.id_empresa
            WHERE ue.id_usuario = :id_usuario
              AND em.id_mina = :id_mina
              AND cc.id_concesion = :id_concesion
              AND cc.estado = :estado
        ) AS autorizado
        ';

        $result = DB::selectOne($sql, [
            'id_usuario' => $id_usuario,
            'id_mina' => $id_mina,
            'id_concesion' => $id_concesion,
            'estado' => EstadoBase::Activo->value,
        ]);

        return (bool) $result->autorizado;
    }

    public static function get_responsables_historial(int $id_mina)
    {
        $sql = '
        SELECT
            rm.id AS id_asignacion,
            rm.id_empleado,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            rm.fecha_inicio,
            rm.fecha_fin,
            rm.estado
        FROM
            responsable_mina rm
        INNER JOIN empleado emp ON emp.id = rm.id_empleado
        WHERE
            rm.id_mina = :id_mina
        ORDER BY rm.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_mina' => $id_mina]);
    }
}
