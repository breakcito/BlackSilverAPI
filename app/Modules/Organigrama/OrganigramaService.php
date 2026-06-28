<?php

namespace App\Modules\Organigrama;

use App\Shared\Responses\ApiResponse;
use App\Modules\Organigrama\Data\CargosData;

class OrganigramaService
{
    public static function cambiar_estado_cargo(int $id_cargo): array|object
    {
        $nuevo_estado = CargosData::cambiar_estado($id_cargo);

        return ApiResponse::success(null, "Cargo marcado como $nuevo_estado");
    }
}
