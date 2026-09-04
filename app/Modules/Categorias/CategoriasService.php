<?php

namespace App\Modules\Categorias;

use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Responses\ApiResponse;
use App\Modules\Categorias\Data\CategoriasData;
use App\Data\CategoriasData as CategoriasDataGlobal;
use App\Services\CategoriasService as CategoriasServiceGlobal;

class CategoriasService
{
    /**
     * Obtener el listado de categorías activas
     */
    public static function get_categorias()
    {
        $categorias = CategoriasData::get_categorias();

        foreach ($categorias as $categoria) {
            $categoria->categorias_consumidoras = [];
        }

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
    ) {
        $response = CategoriasServiceGlobal::crear_categoria(
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
            es_auditable: $es_auditable,
        );

        if ($response['success'] == false) {
            return $response;
        }

        $id_categoria = $response['data'];
        $nuevaCategoria = CategoriasData::get_categoria_by_id($id_categoria);
        if ($nuevaCategoria) {
            $nuevaCategoria->categorias_consumidoras = [];
        }


        return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
    }

    /**
     * Actualizar una categoría existente.
     *
     * Mismo criterio que `crear_categoria`: se persiste lo que envía el
     * cliente (las reglas de UX — consumible solo para Suministro, odómetro
     * junto a transporte — las aplica el formulario). Acá solo se valida
     * existencia y unicidad de nombre.
     */
    public static function actualizar_categoria(
        int $id_categoria,
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
        ?int $id_empleado = null,
        ?string $nombre_empleado = null,
    ) {
        // 1. Validar que la categoría exista
        $existe = CategoriasData::get_categorias(id_categoria: $id_categoria);
        if (! $existe) {
            return ApiResponse::error('La categoría que intenta editar no existe.');
        }

        // 2. Validar nombre único (excluyendo la propia categoría)
        if (CategoriasData::existe_nombre($nombre, excluir_id: $id_categoria)) {
            return ApiResponse::error('Ya existe otra categoría registrada con este nombre.');
        }

        // 3. Persistir cambios (+ diff en cambios_log)
        CategoriasData::actualizar_categoria(
            id_categoria: $id_categoria,
            nombre: $nombre,
            tipo_producto: $tipo_producto->value,
            clasificacion_bien: $clasificacion_bien->value,
            descripcion: $descripcion,
            para_transporte: $para_transporte,
            control_por_odometro: $control_por_odometro,
            control_por_horometro: $control_por_horometro,
            control_por_vueltas: $control_por_vueltas,
            es_consumible: $es_consumible,
            para_cocina: $para_cocina,
            para_mina: $para_mina,
            es_auditable: $es_auditable,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        // 4. Devolver la categoría refrescada (mismo shape que listar)
        $categoria = CategoriasData::get_categoria_by_id($id_categoria);
        if ($categoria) {
            $categoria->categorias_consumidoras = [];
        }

        return ApiResponse::success($categoria, 'Categoría actualizada correctamente');
    }

    /**
     * Desactivar una categoría (soft delete).
     *
     * Se bloquea si aún tiene productos activos: el catálogo auxiliar solo
     * ofrece categorías Activas, así que esos productos quedarían apuntando
     * a una categoría que ya no aparece en el Select al editarlos.
     */
    public static function eliminar_categoria(int $id_categoria)
    {
        $existe = CategoriasData::get_categorias(id_categoria: $id_categoria);
        if (! $existe) {
            return ApiResponse::error('La categoría que intenta eliminar no existe.');
        }

        $productosActivos = CategoriasData::contar_productos_activos($id_categoria);
        if ($productosActivos > 0) {
            return ApiResponse::error(sprintf(
                'No se puede eliminar: la categoría tiene %d producto%s activo%s. Reasígnelos o elimínelos primero.',
                $productosActivos,
                $productosActivos === 1 ? '' : 's',
                $productosActivos === 1 ? '' : 's',
            ));
        }

        CategoriasData::eliminar_categoria(id_categoria: $id_categoria);

        // Devolvemos la categoría ya Inactiva para que el frontend pueda
        // retirarla de la lista con el mismo shape que entrega listar.
        $categoria = CategoriasData::get_categoria_by_id($id_categoria);
        if ($categoria) {
            $categoria->categorias_consumidoras = [];
        }

        return ApiResponse::success($categoria, 'Categoría eliminada correctamente');
    }
}
