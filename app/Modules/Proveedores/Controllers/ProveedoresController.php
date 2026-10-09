<?php

namespace App\Modules\Proveedores\Controllers;

use App\Modules\Proveedores\Services\ProveedoresService;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
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
        $conLugares = !$request->boolean('sin_lugares', false);

        return response()->json(ProveedoresService::get_proveedores(paraCarbon: $paraCarbon, conLugares: $conLugares));
    }

    public function crear_proveedor(Request $request)
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            'paraCarbon' => 'nullable|boolean',
            // RUC opcional; si llega, debe tener 11 digitos con prefijo segun
            // tipo_entidad (se valida abajo). El middleware ConvertEmptyStringsToNull
            // ya normaliza "" a null antes de llegar aca.
            'ruc' => 'nullable|string|size:11',
            // DNI opcional; si llega, debe tener 8 digitos.
            'dni' => 'nullable|string|size:8',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:100',
            // Solo se persisten cuando paraCarbon=true; en otro caso el
            // servicio los ignora (defensa).
            'codigo_reinfo' => 'nullable|string|max:64',
            'contratos' => 'nullable|array',
            'contratos.*.url' => 'required_with:contratos|string|max:2048',
            'contratos.*.path_relativo' => 'required_with:contratos|string|max:512',
            'contratos.*.nombre_original' => 'nullable|string|max:255',
            'contratos.*.extension' => 'nullable|string|max:32',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = $request->input('ruc') ?: null;

        // Validacion de prefijo de RUC segun tipo de entidad. Solo aplica si
        // se ingreso un RUC; cuando es null (persona sin RUC) se omite.
        if (!empty($ruc) && $tipo_entidad === TipoEntidad::Juridica && !str_starts_with($ruc, '20')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona juridica debe comenzar con 20'),
                422,
            );
        }
        if (!empty($ruc) && $tipo_entidad === TipoEntidad::Natural && !str_starts_with($ruc, '10')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona natural debe comenzar con 10'),
                422,
            );
        }

        // Normalizar codigo_reinfo a mayusculas (la UI lo fuerza, pero el backend
        // tambien lo enforza para no depender del cliente).
        $codigoReinfo = $request->input('codigo_reinfo') !== null
            ? mb_strtoupper(trim((string) $request->input('codigo_reinfo')))
            : null;
        $codigoReinfo = ($codigoReinfo === '') ? null : $codigoReinfo;

        // El frontend sube los archivos antes y envia los IArchivo resultantes.
        $contratos = $request->input('contratos');
        $contratos = is_array($contratos) ? $contratos : [];

        return response()->json(ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->razon_social,
            paraMantenimiento: (bool) $request->paraMantenimiento,
            paraTransporte: (bool) $request->paraTransporte,
            dni: $request->dni,
            ruc: $ruc,
            direccion: $request->direccion,
            telefono: $request->telefono,
            correo: $request->correo,
            paraCarbon: (bool) $request->paraCarbon,
            codigoReinfo: $codigoReinfo,
            contratos: $contratos,
            cuentas: $request->cuentas ?? []
        ));
    }

    /**
     * Actualizar un proveedor existente (logística o carbón).
     *
     * `paraCarbon` no se acepta: define en qué pestaña vive el proveedor.
     */
    public function actualizar_proveedor(Request $request, int $id_proveedor)
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            // RUC opcional; si llega, debe tener 11 digitos con prefijo segun
            // tipo_entidad (se valida abajo). El middleware ConvertEmptyStringsToNull
            // ya normaliza "" a null antes de llegar aca.
            'ruc' => 'nullable|string|size:11',
            'dni' => 'nullable|string|size:8',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:100',
            'codigo_reinfo' => 'nullable|string|max:64',
            'contratos' => 'nullable|array',
            'contratos.*.url' => 'required_with:contratos|string|max:2048',
            'contratos.*.path_relativo' => 'required_with:contratos|string|max:512',
            'contratos.*.nombre_original' => 'nullable|string|max:255',
            'contratos.*.extension' => 'nullable|string|max:32',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = $request->input('ruc') ?: null;

        // Mismas reglas de prefijo de RUC que en el registro. Solo aplica
        // si se ingreso un RUC; cuando es null (persona sin RUC) se omite.
        if (! empty($ruc) && $tipo_entidad === TipoEntidad::Juridica && ! str_starts_with($ruc, '20')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona juridica debe comenzar con 20'),
                422,
            );
        }
        if (! empty($ruc) && $tipo_entidad === TipoEntidad::Natural && ! str_starts_with($ruc, '10')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona natural debe comenzar con 10'),
                422,
            );
        }

        $codigoReinfo = $request->input('codigo_reinfo') !== null
            ? mb_strtoupper(trim((string) $request->input('codigo_reinfo')))
            : null;
        $codigoReinfo = ($codigoReinfo === '') ? null : $codigoReinfo;

        $contratos = $request->input('contratos');
        $contratos = is_array($contratos) ? $contratos : [];

        // Identidad del usuario autenticado (la puebla JwtAuthMiddleware).
        // Se usa para el log de cambios.
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado)
            ? (int) $authUser->id_empleado
            : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        return response()->json(ProveedoresService::actualizar_proveedor(
            id_proveedor: $id_proveedor,
            tipoEntidad: $tipo_entidad,
            razonSocial: (string) $request->input('razon_social'),
            dni: $request->input('dni'),
            ruc: $ruc,
            direccion: $request->input('direccion'),
            telefono: $request->input('telefono'),
            correo: $request->input('correo'),
            paraMantenimiento: $request->boolean('paraMantenimiento'),
            paraTransporte: $request->boolean('paraTransporte'),
            codigoReinfo: $codigoReinfo,
            contratos: $contratos,
            idEmpleado: $idEmpleado,
            nombreEmpleado: $nombreEmpleado,
        ));
    }

    /**
     * Eliminar (desactivar) un proveedor
     */
    public function eliminar_proveedor(Request $request, int $id_proveedor)
    {
        return response()->json(
            ProveedoresService::eliminar_proveedor(id_proveedor: $id_proveedor)
        );
    }
}
