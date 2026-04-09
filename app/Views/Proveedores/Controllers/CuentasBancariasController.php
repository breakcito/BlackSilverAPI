<?php

namespace App\Views\Proveedores\Controllers;

use App\Shared\Responses\ApiResponse;
use App\Views\Proveedores\Services\CuentasBancariasService;
use Illuminate\Http\Request;

class CuentasBancariasController
{
    protected CuentasBancariasService $service;

    public function __construct(CuentasBancariasService $service)
    {
        $this->service = $service;
    }

    public function get_cuentas_bancarias(int $id_proveedor)
    {
        $data = $this->service->get_cuentas_bancarias($id_proveedor);
        return ApiResponse::success($data, "Cuentas bancarias obtenidas correctamente");
    }

    public function crear_cuenta_bancaria(Request $request)
    {
        $request->validate([
            'id_proveedor' => 'required|integer',
            'id_banco' => 'required|integer',
            'moneda' => 'required|string|in:Soles,Dólares,Euros',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
            'es_para_detraccion' => 'required|boolean',
        ]);

        $id = $this->service->crear_cuenta_bancaria(
            (int) $request->id_proveedor,
            (int) $request->id_banco,
            $request->moneda,
            $request->numero_cuenta,
            $request->cci,
            (int) $request->es_para_detraccion
        );

        return ApiResponse::success(['id_cuenta_bancaria' => $id], "Cuenta bancaria creada correctamente");
    }
}
