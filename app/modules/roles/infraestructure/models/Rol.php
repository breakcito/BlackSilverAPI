<?php

namespace App\Modules\Roles\Infraestructure\Models;

use App\Enums\EstadoBase;
use App\Modules\Sistema\Infraestructure\Models\Seccion;
use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla rol.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoBase $estado
 */
class Rol extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'rol';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /**
     * Casting de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoBase::class,
        ];
    }

    /**
     * Relación con secciones a través de la tabla pivote.
     */
    public function secciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Seccion::class,
            'seccion_rol',
            'id_rol',
            'id_seccion'
        );
    }

    /**
     * Relación directa con la tabla pivote seccion_rol.
     */
    public function seccionesRol(): HasMany
    {
        return $this->hasMany(SeccionRol::class, 'id_rol');
    }

    /**
     * Relación con usuarios.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol');
    }
}
