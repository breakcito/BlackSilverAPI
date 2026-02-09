<?php

namespace App\Modules\Usuarios\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Usuario extends Model implements AuthenticatableContract, JWTSubject
{
    #region setup
    use Authenticatable;

    protected $table = 'usuario';

    protected $hidden = ['password'];

    public $timestamps = false;
    #endregion

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

    // Buscar usuario por nombre de usuario.
    public static function getByUsername(string $username)
    {
        $sql = '
        SELECT
            usu.id as id_usuario,
            usu.password
        FROM
            usuario usu
        WHERE
            usu.usuario = :usuario AND
            usu.estado = :estado
        LIMIT 1
        ';

        $result = DB::select($sql, [
            'usuario' => $username,
            'estado' => EstadoBase::Activo->value
        ]);

        return $result[0] ?? null;
    }

    // Obtener información del usuario
    public static function getInfoUsuarioById(int $id)
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
            emp.path_foto
        FROM
            usuario usu
        INNER JOIN empleado emp on emp.id = usu.id_empleado
        WHERE
            usu.id = :id
        ';

        $result = DB::select($sql, [
            'id' => $id
        ]);

        return $result[0] ?? null;
    }
}
