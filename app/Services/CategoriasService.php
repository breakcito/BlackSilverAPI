<?php
namespace App\Services;

use App\Data\CategoriasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class CategoriasService
{
    public static function get_categorias(
        ?int $id_categoria = null,
        ?TipoBien $tipo_bien = null,
        ?TipoProducto $tipo_producto = null,
        ?EstadoBase $estado = EstadoBase::Activo
    ) {
        $categorias = CategoriasData::get_categorias(
            id_categoria: $id_categoria,
            tipo_bien: $tipo_bien,
            tipo_producto: $tipo_producto,
            estado: $estado
        );
        return ApiResponse::success($categorias);
    }

    /**
     * Crear una nueva categoría
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
        bool $es_auditable = false,
        array $ids_categorias_consumidoras = [],
        bool $return_object = false
    ) {
        return DB::transaction(function () use ($nombre, $tipo_producto, $clasificacion_bien, $descripcion, $para_transporte, $control_por_odometro, $control_por_horometro, $control_por_vueltas, $es_consumible, $para_cocina, $para_mina, $es_auditable, $ids_categorias_consumidoras, $return_object) {
            if (CategoriasData::ya_existe($nombre)) {
                return ApiResponse::error('Ya existe una categoría con este nombre.');
            }

            // Validación de negocio: Al menos una clasificación debe ser seleccionada
            if (!$para_cocina && !$para_mina) {
                return ApiResponse::error('Debe seleccionar al menos un área (Mina o Cocina) para la categoría.');
            }

            $id_categoria = CategoriasData::crear_categoria(
                nombre: $nombre,
                tipo_producto: $tipo_producto,
                clasificacion_bien: $clasificacion_bien,
                descripcion: $descripcion,
                para_transporte: $para_transporte,
                control_por_odometro: $control_por_odometro,
                control_por_horometro: $control_por_horometro,
                control_por_vueltas: $control_por_vueltas,
                es_consumible: $es_consumible,
                para_cocina: $para_cocina,
                para_mina: $para_mina,
                es_auditable: $es_auditable
            );

            // Si es consumible, guardamos sus relaciones
            if ($es_consumible && !empty($ids_categorias_consumidoras)) {
                CategoriasData::establecer_consumidoras($id_categoria, $ids_categorias_consumidoras);
            }

            if ($return_object) {
                $nuevaCategoria = CategoriasData::get_categorias(id_categoria: $id_categoria);
                return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
            }

            return ApiResponse::success($id_categoria, 'Categoría creada correctamente');
        });
    }
}