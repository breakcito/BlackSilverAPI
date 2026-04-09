<?php

namespace App\Views\Proveedores\Controllers;

use App\Shared\Responses\ApiResponse;
use App\Views\Proveedores\Services\ProveedoresService;
use Illuminate\Http\Request;

class ProveedoresController
{
    protected ProveedoresService $service;

    public function __construct(ProveedoresService $service)
    {
        $this->service = $service;
    }

    public function get_proveedores()
    {
        $data = $this->service->get_proveedores();
        return ApiResponse::success($data, "Proveedores obtenidos correctamente");
    }

    public function crear_proveedor(Request $request)
    {
        $request->validate([
            'tipo_entidad' => 'required|string|in:Natural,Jurídica',
            'dni' => 'nullable|string|max:8',
            'ruc' => 'nullable|string|max:11',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:100',
        ]);

        $id = $this->service->crear_proveedor(
            $request->tipo_entidad,
            $request->dni,
            $request->ruc,
            $request->razon_social,
            $request->direccion,
            $request->telefono,
            $request->correo
        );

        return ApiResponse::success(['id_proveedor' => $id], "Proveedor creado correctamente");
    }
}
