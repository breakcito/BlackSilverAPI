<?php

namespace App\Modules\Proveedores\Controllers;

use App\Models\Proveedor;
use App\Modules\Proveedores\Data\CuentasBancariasData;
use App\Modules\Proveedores\Services\CuentasBancariasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CuentasBancariasController
{
    public function get_cuentas_bancarias(int $id_proveedor): JsonResponse
    {
        return response()->json(CuentasBancariasService::get_cuentas_bancarias($id_proveedor));
    }

    public function crear_cuenta_bancaria(Request $request): JsonResponse
    {
        $request->validate([
            'id_proveedor' => 'required|integer',
            'id_banco' => 'required|integer',
            'moneda' => 'required|string',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
            'es_para_detraccion' => 'required|boolean',
        ]);

        $idProveedor = (int) $request->id_proveedor;
        $moneda = (string) $request->moneda;

        // Defensa final: los proveedores de carbon solo admiten cuentas en Soles.
        if ($this->proveedorEsParaCarbon($idProveedor)) {
            $moneda = 'Soles';
        }

        return response()->json(CuentasBancariasService::crear_cuenta_bancaria(
            $idProveedor,
            (int) $request->id_banco,
            $moneda,
            $request->numero_cuenta,
            $request->cci,
            (int) $request->es_para_detraccion
        ));
    }

    public function actualizar_cuenta_bancaria(Request $request, int $id_cuenta_bancaria): JsonResponse
    {
        $request->validate([
            'id_banco' => 'required|integer',
            'moneda' => 'required|string',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
            'es_para_detraccion' => 'required|boolean',
        ]);

        $moneda = (string) $request->moneda;

        $idProveedor = CuentasBancariasData::get_proveedor_id_by_cuenta($id_cuenta_bancaria);
        if ($idProveedor !== null && $this->proveedorEsParaCarbon($idProveedor)) {
            $moneda = 'Soles';
        }

        return response()->json(CuentasBancariasService::actualizar_cuenta_bancaria(
            $id_cuenta_bancaria,
            (int) $request->id_banco,
            $moneda,
            $request->numero_cuenta,
            $request->cci,
            (int) $request->es_para_detraccion
        ));
    }

    private function proveedorEsParaCarbon(int $idProveedor): bool
    {
        $row = Proveedor::query()
            ->where('id', $idProveedor)
            ->first(['para_carbon']);
        return $row ? (bool) $row->para_carbon : false;
    }
}