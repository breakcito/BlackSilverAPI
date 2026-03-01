<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que ayuda a identificar:
// - Que minas administra una empresa
// - Que empresas administran una mina
class EmpresaMina extends Model
{
    protected $table = 'empresa_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'id_empresa',
    ];

    public static function asignar_empresa(int $id_mina, int $id_empresa)
    {
        return self::insertGetId([
            'id_mina' => $id_mina,
            'id_empresa' => $id_empresa,
        ]);
    }

    public static function desasignar_empresa(int $id_asignacion)
    {
        return self::where('id', $id_asignacion)->delete();
    }

    public static function get_empresas_asignadas(int $id_mina)
    {
        $sql = '
        SELECT
            em.id AS id_asignacion,
            em.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo
        FROM
            empresa_mina em
        INNER JOIN empresa e ON e.id = em.id_empresa
        WHERE
            em.id_mina = :id_mina
        ORDER BY e.nombre_comercial ASC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, ['id_mina' => $id_mina]);
    }

    public static function verificar_empresa_asignada(int $id_mina, int $id_empresa)
    {
        return self::where('id_mina', $id_mina)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }
}
