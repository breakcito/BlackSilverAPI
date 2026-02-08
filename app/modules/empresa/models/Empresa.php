<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

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
    protected $table = 'empresa';

    public $timestamps = false;

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'abreviatura',
        'path_logo',
    ];

    /**
     * Buscar empresa por ID.
     */
    public static function buscarPorId(int $id): ?Empresa
    {
        return self::find($id);
    }
}
