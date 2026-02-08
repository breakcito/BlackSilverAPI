<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla area.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoBase $estado
 */
class Area extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'area';

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
     * Relación con empresas a través de la tabla pivote.
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(
            Empresa::class,
            'area_empresa',
            'id_area',
            'id_empresa'
        );
    }

    /**
     * Relación directa con la tabla pivote.
     */
    public function areasEmpresa(): HasMany
    {
        return $this->hasMany(AreaEmpresa::class, 'id_area');
    }
}
