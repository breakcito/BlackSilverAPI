<?php

namespace App\Modules\Clientes\Controllers;

use App\Modules\Clientes\Services\ClientesService;
use Illuminate\Http\Request;

class ClientesController
{
    /** Retorna la lista completa de clientes. */
    public function get_clientes()
    {
        return response()->json(ClientesService::get_clientes());
    }

    /** Valida la entrada y registra un nuevo cliente. */
    public function crear_cliente(Request $request)
    {
        $request->validate([
            'tipo_entidad'      => 'nullable|string|max:64',
            'dni'               => 'nullable|string|size:8',
            'ruc'               => 'nullable|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        return response()->json(ClientesService::crear_cliente(
            $request->tipo_entidad,
            $request->dni,
            $request->ruc,
            $request->razon_social,
            $request->direccion,
            $request->telefono,
            $request->correo
        ));
    }
}
