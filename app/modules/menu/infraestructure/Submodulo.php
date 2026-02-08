<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla submodulo.
 *
 * Representa los submódulos que pertenecen a un módulo
 * y contienen las secciones del sistema.
 *
 * @property int $id
 * @property int $id_modulo
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoBase $estado
 */
class Submodulo extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'submodulo';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_modulo',
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
     * Relación con el módulo padre.
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'id_modulo');
    }

    /**
     * Relación con secciones.
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'id_submodulo');
    }
}
