<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public static function get_empresas_asignadas(int $id_mina)
    {
        $sql = '
        SELECT
            em.id AS id_empresa_mina,
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

        return DB::select($sql, ['id_mina' => $id_mina]);
    }
}
