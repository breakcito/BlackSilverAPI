<?php

namespace App\Modules\Sistema\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para la tabla pivote accion_sistema_seccion.
 *
 * Define qué acciones están disponibles en cada sección.
 *
 * @property int $id
 * @property int $id_seccion
 * @property int $id_accion_sistema
 * @property bool $es_principal
 */
class AccionSistemaSeccion extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'accion_sistema_seccion';

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
        'id_accion_sistema',
        'es_principal',
    ];

    /**
     * Casting de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
        ];
    }

    /**
     * Relación con la sección.
     */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'id_seccion');
    }

    /**
     * Relación con la acción del sistema.
     */
    public function accionSistema(): BelongsTo
    {
        return $this->belongsTo(AccionSistema::class, 'id_accion_sistema');
    }
}
