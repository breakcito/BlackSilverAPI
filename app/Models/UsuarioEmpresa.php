<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsuarioEmpresa extends Model
{
    protected $table = 'usuario_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_empresa',
    ];

    /**
     * Obtener usuarios por empresa
     */
    public static function get_usuarios_por_empresa(int $id_empresa)
    {
        $sql = '
        SELECT
            ue.id as id_usuario_empresa,
            e.nombre as nombres,
            e.apellido as apellidos,
            c.nombre as cargo,
            u.username
        FROM
            usuario_empresa ue
        INNER JOIN usuario u ON u.id = ue.id_usuario
        INNER JOIN empleado e ON e.id = u.id_empleado
        LEFT JOIN cargo c ON c.id = e.id_cargo
        WHERE
            ue.id_empresa = :id_empresa
        ORDER BY e.apellido ASC
        ';

        return DB::select($sql, [
            'id_empresa' => $id_empresa,
        ]);
    }
}
