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
            p.nombre,
            c.nombre as categoria,
            p.es_perecible,
            p.id_unidad_medida_base,
            um.abreviatura as unidad_medida_base
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
}
