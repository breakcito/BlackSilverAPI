<?php

namespace App\Views\MinasLabores;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class MinasLaboresController extends Controller
{
    // ─── Concesiones ──────────────────────────────────────────────────────────

    public function get_concesiones_sesion(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        return response()->json(MinasLaboresService::get_concesiones_sesion($authUser->id_usuario));
    }

    // ─── Minas ────────────────────────────────────────────────────────────────

    public function get_minas_resumen(Request $request): JsonResponse
    {
        // id_concesion es opcional: si se pasa filtra, si no devuelve todas las minas
        $id_concesion = $request->query('id_concesion');

        return response()->json(MinasLaboresService::get_minas_resumen(
            $id_concesion ? (int) $id_concesion : null
        ));
    }

    public function crear_mina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        return response()->json(MinasLaboresService::crear_mina(
            $request->id_concesion,
            $request->nombre,
            $request->descripcion,
        ));
    }

    // ─── Empresas ejecutoras ──────────────────────────────────────────────────

    public function get_empresas_ejecutoras(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(MinasLaboresService::get_empresas_ejecutoras((int) $id_mina));
    }

    public function get_empresas_disponibles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'id_concesion' => 'required|integer',
            'id_mina' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');

        return response()->json(MinasLaboresService::get_empresas_disponibles(
            (int) $request->query('id_concesion'),
            (int) $request->query('id_mina'),
            (int) $authUser->id_usuario,
        ));
    }

    public function asignar_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empresa' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        return response()->json(MinasLaboresService::asignar_empresa(
            $request->id_mina,
            $request->id_empresa,
        ));
    }

    // ─── Responsables ─────────────────────────────────────────────────────────

    public function get_historial_responsables(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(MinasLaboresService::get_historial_responsables((int) $id_mina));
    }

    public function get_empleados_disponibles(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        $authUser = $request->attributes->get('auth_user');

        return response()->json(MinasLaboresService::get_empleados_disponibles((int) $id_mina, (int) $authUser->id_usuario));
    }

    public function asignar_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empleado' => 'required|integer',
            'fecha_inicio' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        return response()->json(MinasLaboresService::asignar_responsable(
            $request->id_mina,
            $request->id_empleado,
            $request->fecha_inicio,
        ));
    }

    // ─── Labores ──────────────────────────────────────────────────────────────
    public function get_tipos_labor(Request $request): JsonResponse
    {
        return response()->json(MinasLaboresService::get_tipos_labor());
    }

    public function get_labores(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(MinasLaboresService::get_labores((int) $id_mina));
    }

    public function crear_labor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empresa' => 'required|integer',
            'id_tipo_labor' => 'required|integer',
            'nombre' => 'nullable|string|max:128',
            'descripcion' => 'nullable|string',
            'tipo_sostenimiento' => 'required|string',
            'veta' => 'nullable|string|max:45',
            'ancho' => 'nullable|numeric',
            'alto' => 'nullable|numeric',
            'nivel' => 'nullable|string|max:45',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(MinasLaboresService::crear_labor(
            id_mina: (int) $v['id_mina'],
            id_empresa: (int) $v['id_empresa'],
            id_tipo_labor: (int) $v['id_tipo_labor'],
            nombre: (string) $v['nombre'],
            descripcion: isset($v['descripcion']) ? (string) $v['descripcion'] : null,
            tipo_sostenimiento: (string) $v['tipo_sostenimiento'],
            veta: isset($v['veta']) ? (string) $v['veta'] : null,
            ancho: isset($v['ancho']) ? (float) $v['ancho'] : null,
            alto: isset($v['alto']) ? (float) $v['alto'] : null,
            nivel: isset($v['nivel']) ? (string) $v['nivel'] : null,
            fecha_inicio: isset($v['fecha_inicio']) ? (string) $v['fecha_inicio'] : null,
            fecha_fin: isset($v['fecha_fin']) ? (string) $v['fecha_fin'] : null,
        ));
    }
}
