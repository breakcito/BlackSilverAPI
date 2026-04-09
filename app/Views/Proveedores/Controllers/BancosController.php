<?php

namespace App\Views\Proveedores\Controllers;

use App\Shared\Responses\ApiResponse;
use App\Views\Proveedores\Services\BancosService;

class BancosController
{
    protected BancosService $service;

    public function __construct(BancosService $service)
    {
        $this->service = $service;
    }

    public function get_bancos()
    {
        $data = $this->service->get_bancos();
        return ApiResponse::success($data, "Bancos logrados correctamente");
    }

    public function crear_banco(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'abreviatura' => 'required|string|max:20',
        ]);
        $id = $this->service->crear_banco($request->nombre, $request->abreviatura);
        return ApiResponse::success(['id_banco' => $id], "Banco creado correctamente");
    }
}
