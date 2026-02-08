<?php

namespace App\Modules\Roles\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla rol.
 *
 * @property int $id
 * @property string $nombre
 */
class Rol extends Model
{
    protected $table = 'rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Crear un nuevo rol.
     */
    public static function crearRol(string $nombre): ?Rol
    {
        return self::create([
            'nombre' => $nombre,
        ]);
    }

    /**
     * Buscar rol por ID.
     */
    public static function buscarPorId(int $id): ?Rol
    {
        return self::find($id);
    }
}
