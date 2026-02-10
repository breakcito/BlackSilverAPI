<?php

namespace App\Modules\Empresas\Controllers;

use App\Modules\Empresas\Services\EmpresaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmpresaController extends Controller
{
    public function __construct(
        private EmpresaService $empresaService
    ) {}

    public function index(): JsonResponse
    {
        $result = $this->empresaService->get_empresas();
        return response()->json($result);
    }
}
