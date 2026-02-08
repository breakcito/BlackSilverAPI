<?php

namespace App\Modules\Usuarios\Infraestructure\Models;

use App\Modules\Empresa\Infraestructure\Models\Empleado;
use App\Modules\Empresa\Infraestructure\Models\Empresa;
use App\Modules\Roles\Infraestructure\Models\Rol;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Modelo para la tabla usuario.
 *
 * Implementa JWT para autenticación API.
 *
 * @property int $id
 * @property int $id_rol
 * @property int $id_empleado
 * @property string $username
 * @property string $password
 * @property \Carbon\Carbon $created_at
 */
class Usuario extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'usuario';

    /**
     * Indica si el modelo debe tener updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_rol',
        'id_empleado',
        'username',
        'password',
    ];

    /**
     * Atributos ocultos para serialización.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Casting de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Obtener el identificador para JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Claims personalizados para JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'username' => $this->username,
            'id_rol' => $this->id_rol,
        ];
    }

    /**
     * Relación con el rol.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    /**
     * Relación con el empleado.
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    /**
     * Relación con empresas a través de la tabla pivote.
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'usuario_empresa',
            'id_usuario',
            'id_empresa'
        );
    }

    /**
     * Relación directa con la tabla pivote.
     */
    public function usuarioEmpresas(): HasMany
    {
        return $this->hasMany(UsuarioEmpresa::class, 'id_usuario');
    }

    /**
     * Buscar usuario por ID.
     */
    public static function buscarPorId(int $id): ?Usuario
    {
        return self::find($id);
    }

    /**
     * Buscar usuario por username.
     */
    public static function buscarPorUsername(string $username): ?Usuario
    {
        return self::where('username', $username)->first();
    }

    /**
     * Crear un nuevo usuario.
     */
    public static function crearUsuario(
        int $idRol,
        int $idEmpleado,
        string $username,
        string $password
    ): ?Usuario {
        return self::create([
            'id_rol' => $idRol,
            'id_empleado' => $idEmpleado,
            'username' => $username,
            'password' => $password,
        ]);
    }
}
