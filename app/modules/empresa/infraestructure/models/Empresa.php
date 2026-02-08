<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para la tabla empresa.
 *
 * @property int $id
 * @property string $ruc
 * @property string $razon_social
 * @property string $nombre_comercial
 * @property string $abreviatura
 * @property string $path_logo
 */
class Empresa extends Model
{
    /**
     * Nombre de la tabla asociada.
     */
    protected $table = 'empresa';

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
        'ruc',
        'razon_social',
        'nombre_comercial',
        'abreviatura',
        'path_logo',
    ];

    /**
     * Relación con áreas a través de la tabla pivote.
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(
            Area::class,
            'area_empresa',
            'id_empresa',
            'id_area'
        );
    }

    /**
     * Relación directa con la tabla pivote.
     */
    public function areasEmpresa(): HasMany
    {
        return $this->hasMany(AreaEmpresa::class, 'id_empresa');
    }

    /**
     * Relación con usuarios a través de la tabla pivote.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Usuarios\Infraestructure\Models\Usuario::class,
            'usuario_empresa',
            'id_empresa',
            'id_usuario'
        );
    }
}
