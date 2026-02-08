<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla seccion.
 *
 * Representa las secciones dentro de un submódulo,
 * cada sección tiene una URL y contiene acciones del sistema.
 *
 * @property int $id
 * @property int $id_submodulo
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $url
 * @property EstadoBase $estado
 */
class Seccion extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'seccion';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_submodulo',
        'nombre',
        'descripcion',
        'url',
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
     * Relación con el submódulo padre.
     */
    public function submodulo(): BelongsTo
    {
        return $this->belongsTo(Submodulo::class, 'id_submodulo');
    }

    /**
     * Relación con acciones del sistema a través de la tabla pivote.
     */
    public function accionesSistema(): BelongsToMany
    {
        return $this->belongsToMany(
            AccionSistema::class,
            'accion_sistema_seccion',
            'id_seccion',
            'id_accion_sistema'
        )->withPivot('es_principal');
    }

    /**
     * Relación directa con la tabla pivote.
     */
    public function accionesSistemaSeccion(): HasMany
    {
        return $this->hasMany(AccionSistemaSeccion::class, 'id_seccion');
    }

    /**
     * Relación con roles a través de la tabla pivote.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Roles\Infraestructure\Models\Rol::class,
            'seccion_rol',
            'id_seccion',
            'id_rol'
        );
    }
}
