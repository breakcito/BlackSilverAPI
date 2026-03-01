<?php

namespace App\Services;

use App\Models\Almacen;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class AlmacenService
{
    /**
     * Listar todos los almacenes.
     */
    public function get_almacenes()
    {
        $almacenes = Almacen::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén.
     */
    public function crear_almacen(string $nombre, ?string $descripcion, bool $es_principal)
    {
        if (Almacen::where('nombre', $nombre)->where('estado', EstadoBase::Activo->value)->exists()) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $almacen = Almacen::create([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_principal' => $es_principal,
            'estado' => EstadoBase::Activo->value,
        ]);

        return ApiResponse::success($almacen, 'Almacén creado correctamente');
    }

}
