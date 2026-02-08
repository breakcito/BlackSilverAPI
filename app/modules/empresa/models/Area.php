<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla area.
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $estado
 */
class Area extends Model
{
    protected $table = 'area';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'estado',
    ];

    /**
     * Crear un nuevo registro de área.
     */
    public static function crearArea(string $nombre, string $estado = 'Activo'): ?Area
    {
        return self::create([
            'nombre' => $nombre,
            'estado' => $estado,
        ]);
    }

    /**
     * Buscar área por ID.
     */
    public static function buscarPorId(int $id): ?Area
    {
        return self::find($id);
    }
}
