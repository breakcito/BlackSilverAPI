<?php

namespace App\Modules\Empresas\Controller;

use App\Modules\Empresas\Service\CuentasService;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class CuentasController extends Controller
{
    public function actualizar_cuenta(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_banco' => 'required|integer',
            'moneda' => ['required', new Enum(Moneda::class)],
            'numero_cuenta' => 'required|string|max:20',
            'cci' => 'nullable|string|max:23',
            'es_para_detraccion' => 'nullable|boolean',
        ], [
            'id_banco.required' => 'El id_banco es obligatorio',
            'moneda.required' => 'La moneda es obligatoria',
            'numero_cuenta.required' => 'El número de cuenta es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::actualizar_cuenta(
            id_cuenta_bancaria: $id,
            id_banco: $request->input('id_banco'),
            moneda: Moneda::from($request->input('moneda')),
            numero_cuenta: $request->input('numero_cuenta'),
            cci: $request->input('cci'),
            es_para_detraccion: $request->input('es_para_detraccion') ?? false
        );

        return response()->json($result);
    }

    public function cambiar_estado_cuenta(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estado' => ['required', new Enum(EstadoBase::class)],
        ], [
            'estado.required' => 'El estado es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = CuentasService::cambiar_estado_cuenta(
            id_cuenta_bancaria: $id,
            estado: EstadoBase::from($request->input('estado'))
        );

        return response()->json($result);
    }
}
