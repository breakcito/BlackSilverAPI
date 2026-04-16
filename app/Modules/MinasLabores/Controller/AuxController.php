<?php

namespace App\Modules\MinasLabores\Controller;

use App\Modules\MinasLabores\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    // ─── Concesiones ──────────────────────────────────────────────────────────

    public function get_concesiones(Request $request): JsonResponse
    {
        return response()->json(AuxService::get_concesiones());
    }
}
