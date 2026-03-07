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
        'id_unidad_medida_base',
        'nombre',
        'es_fiscalizado',
        'es_perecible',
        'stock_minimo',
        'tiempo_espera_vencimiento',
        'periodo_espera_vencimiento',
        'dias_espera_vencimiento',
        'estado',
    ];

    /**
     * Listar todos los productos del catálogo.
     */
    public static function get_productos()
    {
        $sql = '
        SELECT
            p.id AS id_producto,
            p.id_categoria,
            c.nombre as categoria,
            p.id_unidad_medida_base,
            um.nombre as unidad_medida_base,
            um.abreviatura as unidad_medida_abreviatura,
            p.nombre,
            p.es_fiscalizado,
            p.es_perecible,
            p.stock_minimo,
            p.tiempo_espera_vencimiento,
            p.periodo_espera_vencimiento,
            p.dias_espera_vencimiento,
            p.estado
        FROM
            producto p
        INNER JOIN categoria c ON c.id = p.id_categoria
        LEFT JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
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
            p.id_unidad_medida_base,
            um.nombre as unidad_medida_base,
            um.abreviatura as unidad_medida_abreviatura,
            p.nombre,
            p.es_fiscalizado,
            p.es_perecible,
            p.stock_minimo,
            p.tiempo_espera_vencimiento,
            p.periodo_espera_vencimiento,
            p.dias_espera_vencimiento,
            p.estado
        FROM
            producto p
        INNER JOIN categoria c ON c.id = p.id_categoria
        LEFT JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
        WHERE
            p.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    /**
     * Obtener productos disponibles para sugerir.
     */
    public static function get_productos_para_lote()
    {
        $sql = '
        SELECT
            p.id AS id_producto,
            CONCAT(p.nombre, \' - \', um.abreviatura) AS nombre,
            c.nombre as categoria,
            p.es_perecible,
            p.id_unidad_medida_base,
            um.abreviatura as unidad_medida_base,
            um.nombre as nombre_unidad_medida_base,
            p.stock_minimo
        FROM
            producto p
        INNER JOIN categoria c ON c.id = p.id_categoria
        LEFT JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
        WHERE
            p.estado = :estado AND
            c.tipo_requerimiento = :tipo_bien
        ORDER BY p.nombre ASC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value,
            'tipo_bien' => 'Bien',
        ]);
    }

    /**
     * Obtener la abreviatura de la unidad de medida base de un producto.
     */
    public static function get_abreviatura_unidad_base(int $id_producto): string
    {
        return DB::table('producto as p')
            ->join('unidad_medida as um', 'um.id', '=', 'p.id_unidad_medida_base')
            ->where('p.id', $id_producto)
            ->value('um.abreviatura') ?? '';
    }

    // Obtener toda la lista de productos (bienes) junto a su unidad de medida
    public static function get_productos_basic()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.nombre,
            uni.abreviatura as unidad_medida
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        WHERE 
            pr.estado = "Activo" AND
            cat.tipo_requerimiento = "Bien"
        ';

        return DB::select($sql);
    }
}
