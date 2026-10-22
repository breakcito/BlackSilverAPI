<?php

namespace App\Data;

use App\Models\Marca;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class MarcasData
{

    /**
     * Crear una marca
     *
     * @param bool $de_vehiculos Marca del catalogo de vehiculos (camiones,
     *                          tolvas, etc). Las marcas de vehiculo se listan
     *                          antes que el resto.
     */
    public static function crear_marca(
        ?string $nombre = null,
        bool $de_vehiculos = false,
    ) {
        return Marca::insertGetId([
            'nombre' => $nombre,
            'de_vehiculos' => $de_vehiculos ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si existe una marca con el mismo nombre
     */
    public static function existe_marca(string $nombre): bool
    {
        return Marca::where('nombre', $nombre)->exists();
    }

    /**
     * Listar marcas
     *
     * Las marcas de vehiculo se ordenan primero (`de_vehiculos DESC`) y, a
     * igualdad, por nombre.
     */
    public static function get_marcas(
        ?int $id_marca = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?bool $de_vehiculos = null
    ) {
        $sql = '
        SELECT
            mr.id AS id_marca,
            mr.nombre,
            mr.de_vehiculos
        FROM
            marca mr
        WHERE 1=1
        ';

        $params = [];

        if ($id_marca) {
            $sql .= ' AND mr.id = :id_marca';
            $params['id_marca'] = $id_marca;
            return DB::selectOne($sql, $params);
        }

        if ($estado != null) {
            $sql .= ' AND mr.estado = :estado';
            $params['estado'] = $estado->value;
        }

        if ($de_vehiculos !== null) {
            $sql .= ' AND mr.de_vehiculos = :de_vehiculos';
            $params['de_vehiculos'] = $de_vehiculos ? 1 : 0;
        }

        $sql .= ' ORDER BY mr.de_vehiculos DESC, mr.nombre ASC';

        return DB::select($sql, $params);
    }
}
