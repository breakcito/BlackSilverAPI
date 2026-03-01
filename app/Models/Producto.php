<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Producto extends Model
{
    protected $table = 'producto';

    public $timestamps = false;

    protected $fillable = [
        'id_categoria',
        // indica la unidad de medida base que se usa para
        // manipular/calcula/estimar en el dia a dia
        'id_unidad_medida_base',
        'nombre',
        'es_fiscalizado',
        'es_perecible',
        'stock_minimo',
        'estado',
    ];

    /**
     * Listar todos los productos del catálogo.
     * Incluye el nombre de la categoría asociada.
     */
    public static function get_productos()
    {
        $sql = '
        SELECT
            p.id AS id_producto,
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

    public static function get_producto_by_id(int $id)
    {
        $sql = '
        SELECT
            p.id AS id_producto,
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
            p.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }
}
