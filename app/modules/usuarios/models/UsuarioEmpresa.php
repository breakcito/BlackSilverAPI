<?php

namespace App\Modules\Usuarios\Infraestructure\Models;

use App\Modules\Empresa\Infraestructure\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla usuario_empresa.
 *
 * @property int $id
 * @property int $id_usuario
 * @property int $id_empresa
 */
class UsuarioEmpresa extends Model
{
    protected $table = 'usuario_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_empresa',
    ];

    /**
     * Relación con usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    /**
     * Relación con empresa.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Crear asociación usuario-empresa.
     */
    public static function crearUsuarioEmpresa(int $idUsuario, int $idEmpresa): ?UsuarioEmpresa
    {
        return self::create([
            'id_usuario' => $idUsuario,
            'id_empresa' => $idEmpresa,
        ]);
    }

    /**
     * Buscar por usuario y empresa.
     */
    public static function buscarPorUsuarioYEmpresa(int $idUsuario, int $idEmpresa): ?UsuarioEmpresa
    {
        return self::where('id_usuario', $idUsuario)
            ->where('id_empresa', $idEmpresa)
            ->first();
    }
}
