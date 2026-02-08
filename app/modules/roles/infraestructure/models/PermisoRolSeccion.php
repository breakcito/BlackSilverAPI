<?php

namespace App\Modules\Roles\Infraestructure\Models;

use App\Modules\Sistema\Infraestructure\Models\AccionSistemaSeccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla permiso_rol_seccion.
 *
 * Define qué acciones puede realizar un rol en una sección.
 *
 * @property int $id
 * @property int $id_seccion_rol
 * @property int $id_accion_sistema_seccion
 */
class PermisoRolSeccion extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'permiso_rol_seccion';

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
        'id_seccion_rol',
        'id_accion_sistema_seccion',
    ];

    /**
     * Relación con seccion_rol.
     */
    public function seccionRol(): BelongsTo
    {
        return $this->belongsTo(SeccionRol::class, 'id_seccion_rol');
    }

    /**
     * Relación con accion_sistema_seccion.
     */
    public function accionSistemaSeccion(): BelongsTo
    {
        return $this->belongsTo(AccionSistemaSeccion::class, 'id_accion_sistema_seccion');
    }
}
