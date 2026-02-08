<?php

namespace App\Modules\Usuarios\Infraestructure\Models;

use App\Modules\Empresa\Infraestructure\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla pivote usuario_empresa.
 *
 * @property int $id
 * @property int $id_usuario
 * @property int $id_empresa
 */
class UsuarioEmpresa extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'usuario_empresa';

    /**
     * Indica si el modelo debe tener timestamps.
     */
    public $timestamps = false;

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_usuario',
        'id_empresa',
    ];

    /**
     * Relación con el usuario.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    /**
     * Relación con la empresa.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
