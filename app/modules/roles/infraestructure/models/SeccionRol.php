<?php

namespace App\Modules\Roles\Infraestructure\Models;

use App\Modules\Sistema\Infraestructure\Models\Seccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla pivote seccion_rol.
 *
 * Define qué secciones tiene acceso cada rol.
 *
 * @property int $id
 * @property int $id_seccion
 * @property int $id_rol
 */
class SeccionRol extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'seccion_rol';

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
        'id_seccion',
        'id_rol',
    ];

    /**
     * Relación con la sección.
     */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'id_seccion');
    }

    /**
     * Relación con el rol.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    /**
     * Relación con los permisos de esta seccion-rol.
     */
    public function permisos(): HasMany
    {
        return $this->hasMany(PermisoRolSeccion::class, 'id_seccion_rol');
    }
}
