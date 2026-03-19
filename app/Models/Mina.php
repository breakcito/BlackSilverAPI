<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Mina extends Model
{
    protected $table = 'mina';

    public $timestamps = false;

    protected $fillable = [
        'id_concesion',
        'nombre',
        'descripcion',
        'estado',
    ];

    // Listar minas 
    public static function get_minas(?int $id_mina = null, ?int $id_concesion = null)
    {
        $sql = '
        SELECT
            m.id AS id_mina,
            m.id_concesion,
            c.nombre AS concesion,
            m.nombre,
            m.descripcion,
            m.estado,
            (SELECT COUNT(*) FROM empresa_mina em WHERE em.id_mina = m.id) AS empresas_count,
            (SELECT COUNT(*) FROM labor l WHERE l.id_mina = m.id AND l.estado != "Inactivo") AS labores_count,
            (
                SELECT CONCAT(emp.nombre, " ", emp.apellido)
                FROM responsable_mina rm
                INNER JOIN empleado emp ON emp.id = rm.id_empleado
                WHERE rm.id_mina = m.id AND rm.estado = "Activo"
                LIMIT 1
            ) AS responsable_actual
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_concesion) {
            $sql .= ' AND m.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }
        if ($id_mina) {
            $sql .= ' AND m.id = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY m.nombre ASC';

        return DB::select($sql, $params);
    }
}
