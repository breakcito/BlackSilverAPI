<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla accion_sistema.
 *
 * Define las acciones disponibles en el sistema
 * que pueden ser asignadas a secciones.
 *
 * @property int $id
 * @property string $nombre
 * @property string $endpoint
 * @property bool $es_publico
 * @property EstadoBase $estado
 */
class AccionSistema extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'accion_sistema';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'endpoint',
        'es_publico',
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
            'es_publico' => 'boolean',
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
            'accion_sistema_seccion',
            'id_accion_sistema',
            'id_seccion'
        )->withPivot('es_principal');
    }

    /**
     * Relación directa con la tabla pivote.
     */
    public function accionesSistemaSeccion(): HasMany
    {
        return $this->hasMany(AccionSistemaSeccion::class, 'id_accion_sistema');
    }
}
