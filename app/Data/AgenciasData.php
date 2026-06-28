<?php

namespace App\Data;

use App\Models\AgenciaTransporte;
use Illuminate\Support\Facades\DB;

class AgenciasData
{
    /**
     * Listar agencias de transporte
     */
    public static function get_agencias(?int $id_agencia = null)
    {
        $sql = '
        SELECT 
            id AS id_agencia,
            razon_social
        FROM agencia_transporte
        ';

        $params = [];

        if ($id_agencia !== null) {
            $sql .= ' WHERE id = :id_agencia';
            $params['id_agencia'] = $id_agencia;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY razon_social ASC';

        return DB::select($sql, $params);
    }

    /**
     * Crear una agencia
     */
    public static function crear_agencia(string $razon_social): int
    {
        return AgenciaTransporte::insertGetId([
            'razon_social' => $razon_social,
        ]);
    }

    /**
     * Verificar si ya existe por razon social
     */
    public static function ya_existe(string $razon_social): bool
    {
        return AgenciaTransporte::where('razon_social', $razon_social)->exists();
    }
}
