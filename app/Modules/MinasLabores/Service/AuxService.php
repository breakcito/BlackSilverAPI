<?php

namespace App\Modules\MinasLabores\Service;

use App\Modules\MinasLabores\Data\AuxData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    // ─── Concesiones ──────────────────────────────────────────────────────────

    public static function get_concesiones(): array|object
    {
        $concesiones = AuxData::get_concesiones();

        return ApiResponse::success($concesiones);
    }
}
