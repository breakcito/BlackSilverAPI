<?php

namespace App\Modules\Empleados\Infraestructure\Models;

use App\Enums\EstadoBase;
use App\Modules\Empresa\Infraestructure\Models\AreaEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo para la tabla empleado.
 *
 * @property int $id
 * @property int $id_area_empresa
 * @property int $id_cargo
 * @property string $nombre
 * @property string $apellido
 * @property string|null $dni
 * @property string|null $ruc
 * @property string|null $carnet_extranjeria
 * @property string|null $pasaporte
 * @property \Carbon\Carbon|null $fecha_nacimiento
 * @property EstadoBase $estado
 */
class Empleado extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'empleado';

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
        'id_area_empresa',
        'id_cargo',
        'nombre',
        'apellido',
        'dni',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
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
            'fecha_nacimiento' => 'date',
            'estado' => EstadoBase::class,
        ];
    }

    /**
     * Relación con area_empresa.
     */
    public function areaEmpresa(): BelongsTo
    {
        return $this->belongsTo(AreaEmpresa::class, 'id_area_empresa');
    }

    /**
     * Relación con el cargo.
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'id_cargo');
    }

    /**
     * Relación con usuario.
     */
    public function usuario(): HasOne
    {
        return $this->hasOne(\App\Modules\Usuarios\Infraestructure\Models\Usuario::class, 'id_empleado');
    }
}
