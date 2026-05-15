<?php
namespace App\Services;

use App\Data\ProductosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Responses\ApiResponse;

class ProductosService
{
    /**
     * Listar productos
     */
    public static function get_productos(
        ?EstadoBase $estado = EstadoBase::Activo,
        ?bool $con_categorias_consumidoras = false,
        ?TipoBien $tipo_bien = null
    ) {
        $productos = ProductosData::get_productos(
            estado: $estado,
            con_categorias_consumidoras: $con_categorias_consumidoras,
            tipo_bien: $tipo_bien
        );

        return ApiResponse::success($productos);
    }
}