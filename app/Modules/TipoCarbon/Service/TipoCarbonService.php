<?php

namespace App\Modules\TipoCarbon\Service;

use App\Modules\TipoCarbon\Data\TipoCarbonData;
use App\Shared\Responses\ApiResponse;

class TipoCarbonService
{
    public static function get_tipos(?bool $solo_para_compra = null)
    {
        return ApiResponse::success(
            TipoCarbonData::get_tipos(solo_para_compra: $solo_para_compra)
        );
    }

    public static function get_tipo_by_id(int $id_tipo_carbon)
    {
        return ApiResponse::success(TipoCarbonData::get_tipo_by_id(id_tipo_carbon: $id_tipo_carbon));
    }

    public static function crear_tipo(string $nombre, ?string $codigo, bool $para_compra = false)
    {
        $id = TipoCarbonData::crear_tipo(nombre: $nombre, codigo: $codigo, para_compra: $para_compra);
        return ApiResponse::success(TipoCarbonData::get_tipo_by_id(id_tipo_carbon: $id), 'Tipo de carbon registrado');
    }

    public static function actualizar_tipo(int $id_tipo_carbon, string $nombre, ?string $codigo, bool $para_compra = false)
    {
        TipoCarbonData::actualizar_tipo(
            id_tipo_carbon: $id_tipo_carbon,
            nombre: $nombre,
            codigo: $codigo,
            para_compra: $para_compra
        );
        return ApiResponse::success(TipoCarbonData::get_tipo_by_id(id_tipo_carbon: $id_tipo_carbon), 'Tipo de carbon actualizado');
    }

    public static function eliminar_tipo(int $id_tipo_carbon)
    {
        $referencias = TipoCarbonData::contar_referencias_como_variante(id_tipo_carbon: $id_tipo_carbon);
        if ($referencias > 0) {
            return ApiResponse::error(
                'No se puede eliminar: este tipo es variante de ' . $referencias . ' otro(s) tipo(s). Quite las referencias primero.'
            );
        }
        TipoCarbonData::eliminar_tipo(id_tipo_carbon: $id_tipo_carbon);
        return ApiResponse::success(null, 'Tipo de carbon eliminado');
    }

    public static function get_variantes(int $id_tipo_carbon)
    {
        return ApiResponse::success(TipoCarbonData::get_variantes_de_tipo(id_tipo_carbon: $id_tipo_carbon));
    }

    public static function get_variantes_opciones(int $id_tipo_carbon)
    {
        return ApiResponse::success(TipoCarbonData::get_todos_los_tipos());
    }

    /**
     * Set masivo de variantes. Reemplaza todas las asociaciones existentes.
     * @param int[] $ids_variante
     */
    public static function set_variantes(int $id_tipo_carbon, array $ids_variante)
    {
        TipoCarbonData::set_variantes(id_tipo_carbon: $id_tipo_carbon, ids_variante: $ids_variante);
        return ApiResponse::success(
            TipoCarbonData::get_variantes_de_tipo(id_tipo_carbon: $id_tipo_carbon),
            'Variantes actualizadas'
        );
    }
}