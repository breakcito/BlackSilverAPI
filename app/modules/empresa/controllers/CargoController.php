<?php

namespace App\Modules\Empresa\Presentation\Controllers;

use App\Modules\Empresa\Presentation\Requests\StoreAreaEmpresaRequest;
use App\Modules\Empresa\Presentation\Requests\StoreAreaRequest;
use App\Modules\Empresa\Presentation\Requests\StoreCargoEmpresaRequest;
use App\Modules\Empresa\Presentation\Requests\StoreCargoRequest;
use App\Modules\Empresa\Services\CargoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de cargos, áreas y sus asociaciones.
 */
class CargoController extends Controller
{
    public function __construct(
        private CargoService $cargoService
    ) {}

    /**
     * Registrar una nueva área.
     */
    public function storeArea(StoreAreaRequest $request): \Illuminate\Http\JsonResponse
    {
        $result = $this->cargoService->registrarArea(
            $request->validated('nombre')
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::created($result['data'], $result['message']);
    }

    /**
     * Registrar un nuevo cargo.
     */
    public function storeCargo(StoreCargoRequest $request): \Illuminate\Http\JsonResponse
    {
        $result = $this->cargoService->registrarCargo(
            $request->validated('id_area'),
            $request->validated('nombre')
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::created($result['data'], $result['message']);
    }

    /**
     * Registrar asociación área-empresa.
     */
    public function storeAreaEmpresa(StoreAreaEmpresaRequest $request): \Illuminate\Http\JsonResponse
    {
        $result = $this->cargoService->registrarAreaEmpresa(
            $request->validated('id_area'),
            $request->validated('id_empresa')
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::created($result['data'], $result['message']);
    }

    /**
     * Registrar asociación cargo-empresa.
     */
    public function storeCargoEmpresa(StoreCargoEmpresaRequest $request): \Illuminate\Http\JsonResponse
    {
        $result = $this->cargoService->registrarCargoEmpresa(
            $request->validated('id_area_empresa'),
            $request->validated('id_cargo')
        );

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::created($result['data'], $result['message']);
    }
}
