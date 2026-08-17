<?php

namespace App\Modules\Proveedores\Controllers;

use App\Modules\Proveedores\Services\ProveedoresService;
use App\Shared\Enums\_Generic\TipoEntidad;
use Illuminate\Http\Request;

class ProveedoresController
{
    public function get_proveedores(Request $request)
    {
        // $request->boolean() parsea correctamente "true"/"1" como true y
        // "false"/"0" como false. (bool) "false" devolveria true en PHP,
        // por eso NO se usa el cast directo.
        $paraCarbon = $request->has('para_carbon')
            ? $request->boolean('para_carbon')
            : null;

        return response()->json(ProveedoresService::get_proveedores(paraCarbon: $paraCarbon));
    }

    public function crear_proveedor(Request $request)
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            'paraCarbon' => 'nullable|boolean',
            'dni' => 'nullable|string|size:8',
            'ruc' => 'nullable|string|size:11',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:100',
            // geografia (opcional siempre — el switch Logistica/Carbon del front
            // decide si el usuario los completa o no)
            'id_departamento' => 'nullable|integer',
            'id_provincia' => 'nullable|integer',
            'id_distrito' => 'nullable|integer',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));

        return response()->json(ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->razon_social,
            paraMantenimiento: (bool) $request->paraMantenimiento,
            paraTransporte: (bool) $request->paraTransporte,
            dni: $request->dni,
            ruc: $request->ruc,
            direccion: $request->direccion,
            telefono: $request->telefono,
            correo: $request->correo,
            paraCarbon: (bool) $request->paraCarbon,
            id_departamento: $request->id_departamento ? (int) $request->id_departamento : null,
            id_provincia: $request->id_provincia ? (int) $request->id_provincia : null,
            id_distrito: $request->id_distrito ? (int) $request->id_distrito : null,
            cuentas: $request->cuentas ?? []
        ));
    }
}
