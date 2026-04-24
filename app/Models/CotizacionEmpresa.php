<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla que permite asociar varias empresas a una cotizacion
 */
class CotizacionEmpresa extends Model
{
    protected $table = 'cotizacion_empresa';

    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_empresa'
    ];

    public static function asignar_empresa(
        int $id_cotizacion,
        array $ids_empresas
    ): bool {
        // Si pasas un solo registro asociativo, lo convertimos en un arreglo de arreglos.
        if (!isset($ids_empresas[0]) || !is_array($ids_empresas[0])) {
            $ids_empresas = [$ids_empresas];
        }

        $insertData = [];

        foreach ($ids_empresas as $id_emp) {
            $insertData[] = [
                'id_cotizacion' => $id_cotizacion,
                'id_empresa' => (int) $id_emp,
            ];
        }

        // Ejecuta todo en una sola consulta a la base de datos
        return self::insert($insertData);
    }
}
