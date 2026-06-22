<?php

namespace App\Modules\MinasLabores\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\MinasLabores\Data\MinasData;

class MinasService
{

    // ─── Minas ────────────────────────────────────────────────────────────────

    public static function get_minas_resumen(?int $id_concesion = null): array|object
    {
        $minas = MinasData::get_resumen_minas($id_concesion);

        return ApiResponse::success($minas);
    }

    public static function crear_mina(int $id_concesion, string $nombre, ?string $descripcion): array|object
    {
        if (MinasData::existe_nombre($id_concesion, $nombre)) {
            return ApiResponse::error('Ya existe una mina con ese nombre en esta concesión.');
        }

        $id_mina = MinasData::crear_mina($id_concesion, $nombre, $descripcion);
        $creada = MinasData::get_mina_by_id($id_mina);

        return ApiResponse::success($creada, 'Mina creada correctamente');
    }
}
