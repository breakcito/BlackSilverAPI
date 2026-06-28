<?php

namespace App\Data;

use App\Models\Categoria;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoProducto;
use Illuminate\Support\Facades\DB;

class CategoriasData
{

    /**
     * Obtener categorías de tipo "Bien" internas para esta vista
     */
    public static function get_categorias(
        ?int $id_categoria = null,
        ?TipoBien $tipo_bien = null,
        ?TipoProducto $tipo_producto = null,
        ?EstadoBase $estado = EstadoBase::Activo
    ) {
        $sql = '
        SELECT 
            id AS id_categoria, 
            nombre,
            es_auditable,
            clasificacion_bien
        FROM categoria
        WHERE 1=1
        ';


        $params = [];
        if ($id_categoria !== null) {
            $sql .= ' AND id = :id_categoria';
            $params['id_categoria'] = $id_categoria;
            return DB::selectOne($sql, $params);
        }
        if ($tipo_bien !== null) {
            $sql .= ' AND clasificacion_bien = :tipo_bien';
            $params['tipo_bien'] = $tipo_bien->value;
        }
        if ($tipo_producto !== null) {
            $sql .= ' AND tipo_producto = :tipo_producto';
            $params['tipo_producto'] = $tipo_producto->value;
        }
        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY nombre ASC';
        return DB::select($sql, $params);
    }


    /**
     * Crear una nueva categoría con parámetros explícitos
     */
    public static function crear_categoria(
        string $nombre,
        TipoProducto $tipo_producto,
        TipoBien $clasificacion_bien,
        ?string $descripcion = null,
        bool $para_transporte = false,
        bool $control_por_odometro = false,
        bool $control_por_horometro = false,
        bool $control_por_vueltas = false,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false,
        bool $es_auditable = false
    ) {
        return Categoria::insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_producto' => $tipo_producto->value,
            'clasificacion_bien' => $clasificacion_bien->value,
            'para_transporte' => $para_transporte ? 1 : 0,
            'control_por_odometro' => $control_por_odometro ? 1 : 0,
            'control_por_horometro' => $control_por_horometro ? 1 : 0,
            'control_por_vueltas' => $control_por_vueltas ? 1 : 0,
            'es_consumible' => $es_consumible ? 1 : 0,
            'para_cocina' => $para_cocina ? 1 : 0,
            'para_mina' => $para_mina ? 1 : 0,
            'es_auditable' => $es_auditable ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
    /**
     * Verificar si ya existe una categoría con el mismo nombre
     */
    public static function ya_existe(string $nombre)
    {
        return Categoria::where('nombre', $nombre)
            ->exists();
    }
}
