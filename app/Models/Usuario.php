<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Usuario extends Model implements AuthenticatableContract, JWTSubject
{
    // region setup
    use Authenticatable;

    protected $table = 'usuario';

    public $timestamps = false;

    protected $fillable = [
        'id_rol',
        'id_empleado',
        'username',
        'password',
        'estado',
    ];

    protected $hidden = ['password'];

    // Obtener el identificador para JWT.
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    // Array con los valores personalizados para el JWT.
    public function getJWTCustomClaims(): array
    {
        return [];
    }
    // endregion

    // Obtener información del usuario
    public static function getInfoUsuarioById(int $id_usuario)
    {
        $sql = '
        SELECT
            usu.id as id_usuario,
            usu.id_rol,
            usu.id_empleado,
            emp.nombre,
            emp.apellido,
            emp.dni,
            emp.ruc,
            emp.carnet_extranjeria,
            emp.pasaporte,
            emp.fecha_nacimiento,
            emp.path_foto,
            usu.estado
        FROM
            usuario usu
        INNER JOIN empleado emp on emp.id = usu.id_empleado
        WHERE
            usu.id = :id_usuario
        ';

        $result = DB::select($sql, [
            'id_usuario' => $id_usuario,
        ]);

        return $result[0] ?? null;
    }
}
