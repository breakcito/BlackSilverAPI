<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use App\Modules\Empleados\Infraestructure\Models\Empleado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla pivote area_empresa.
 *
 * @property int $id
 * @property int $id_area
 * @property int $id_empresa
 */
class AreaEmpresa extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'area_empresa';

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
        'id_area',
        'id_empresa',
    ];

    /**
     * Relación con el área.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    /**
     * Relación con la empresa.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Relación con empleados.
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'id_area_empresa');
    }
}
