<?php

namespace App\Modules\Empresa\Infraestructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la tabla area_empresa.
 *
 * @property int $id
 * @property int $id_area
 * @property int $id_empresa
 * @property string|null $estado
 */
class AreaEmpresa extends Model
{
    protected $table = 'area_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_area',
        'id_empresa',
        'estado',
    ];

    /**
     * Crear un nuevo registro de área por empresa.
     */
    public static function crearAreaEmpresa(int $idArea, int $idEmpresa, string $estado = 'Activo'): ?AreaEmpresa
    {
        return self::create([
            'id_area' => $idArea,
            'id_empresa' => $idEmpresa,
            'estado' => $estado,
        ]);
    }

    /**
     * Buscar área-empresa por ID.
     */
    public static function buscarPorId(int $id): ?AreaEmpresa
    {
        return self::find($id);
    }

    /**
     * Buscar por combinación de área y empresa.
     */
    public static function buscarPorAreaYEmpresa(int $idArea, int $idEmpresa): ?AreaEmpresa
    {
        return self::where('id_area', $idArea)
            ->where('id_empresa', $idEmpresa)
            ->first();
    }
}
