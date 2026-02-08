<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla cargo_empresa.
 *
 * @property int $id
 * @property int $id_cargo
 * @property int $id_area_empresa
 */
class CargoEmpresa extends Model
{
    protected $table = 'cargo_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo',
        'id_area_empresa',
    ];

    /**
     * Crear un nuevo registro de cargo por empresa.
     */
    public static function crearCargoEmpresa(int $idAreaEmpresa, int $idCargo): ?CargoEmpresa
    {
        return self::create([
            'id_area_empresa' => $idAreaEmpresa,
            'id_cargo' => $idCargo,
        ]);
    }

    /**
     * Buscar cargo-empresa por ID.
     */
    public static function buscarPorId(int $id): ?CargoEmpresa
    {
        return self::find($id);
    }
}
