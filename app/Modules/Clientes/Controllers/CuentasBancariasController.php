<?php

namespace App\Modules\Clientes\Controllers;

use App\Modules\Clientes\Services\CuentasBancariasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CuentasBancariasController
{
    public function get_cuentas_bancarias(int $id_cliente): JsonResponse
    {
        return response()->json(CuentasBancariasService::get_cuentas_bancarias($id_cliente));
    }

    public function crear_cuenta_bancaria(Request $request): JsonResponse
    {
        $request->validate([
            'id_cliente' => 'required|integer',
            'id_banco' => 'required|integer',
            'moneda' => 'required|string',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
            'es_para_detraccion' => 'required|boolean',
        ]);

        return response()->json(CuentasBancariasService::crear_cuenta_bancaria(
            (int) $request->id_cliente,
            (int) $request->id_banco,
            $request->moneda,
            $request->numero_cuenta,
            $request->cci,
            (int) $request->es_para_detraccion
        ));
    }
}
