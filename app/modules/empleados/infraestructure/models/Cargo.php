<?php

namespace App\Modules\Empleados\Infraestructure\Models;

use App\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla cargo.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoBase $estado
 */
class Cargo extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'cargo';

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
     * Relación con empleados.
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'id_cargo');
    }
}
