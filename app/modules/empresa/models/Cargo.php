<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla cargo.
 *
 * @property int $id
 * @property int $id_area
 * @property string $nombre
 * @property string|null $estado
 */
class Cargo extends Model
{
    protected $table = 'cargo';

    public $timestamps = false;

    protected $fillable = [
        'id_area',
        'nombre',
        'estado',
    ];

    /**
     * Crear un nuevo registro de cargo.
     */
    public static function crearCargo(int $idArea, string $nombre, string $estado = 'Activo'): ?Cargo
    {
        return self::create([
            'id_area' => $idArea,
            'nombre' => $nombre,
            'estado' => $estado,
        ]);
    }

    /**
     * Buscar cargo por ID.
     */
    public static function buscarPorId(int $id): ?Cargo
    {
        return self::find($id);
    }
}
