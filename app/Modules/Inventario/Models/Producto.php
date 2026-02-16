<?php

namespace App\Modules\Inventario\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Producto extends Model
{
    /**
     * Listar todos los productos del catálogo.
     * Incluye el nombre de la categoría asociada.
     */
    public static function get_productos()
    {
        $sql = '
        SELECT
            p.id,
            p.id_categoria,
            c.nombre as categoria,
            p.nombre,
            p.es_fiscalizado,
            p.es_perecible,
            p.estado
        FROM
            producto p
        INNER JOIN categoria c ON c.id = p.id_categoria
        WHERE
            p.estado != :estado_inactivo
        ORDER BY p.nombre ASC
        ';

        return DB::select($sql, ['estado_inactivo' => EstadoBase::Inactivo->value]);
    }

    /**
     * Verificar nombre único de producto.
     */
    public static function verificar_producto_existente(string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('producto')
            ->where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    /**
     * Crear un nuevo producto.
     */
    public static function crear_producto(
        int $id_categoria,
        string $nombre,
        bool $es_fiscalizado,
        bool $es_perecible
    ) {
        return DB::table('producto')->insertGetId([
            'id_categoria'    => $id_categoria,
            'nombre'          => $nombre,
            'es_fiscalizado'  => $es_fiscalizado ? 1 : 0,
            'es_perecible'    => $es_perecible ? 1 : 0,
            'estado'          => EstadoBase::Activo->value
        ]);
    }
}
