<?php

namespace App\Modules\Personal\Services;

use App\Modules\Personal\Models\Cargo;
use App\Shared\Responses\ApiResponse;

class CargoService
{
    public function get_cargos()
    {
        return ApiResponse::success(Cargo::get_cargos());
    }
}
