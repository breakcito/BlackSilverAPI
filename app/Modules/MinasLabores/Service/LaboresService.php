<?php

namespace App\Modules\MinasLabores\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\MinasLabores\Data\LaboresData;

class LaboresService
{

    public static function get_tipos_labor(): array|object
    {
        return ApiResponse::success(LaboresData::get_tipos_labor());
    }

    public static function get_labores(int $id_mina): array|object
    {
        return ApiResponse::success(LaboresData::get_historial_labores($id_mina));
    }

    public static function crear_labor(
        int $id_mina,
        int $id_empresa,
        int $id_tipo_labor,
        ?string $nombre,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta,
        ?float $ancho,
        ?float $alto,
        ?string $nivel,
        ?string $fecha_inicio,
        ?string $fecha_fin_estimada = null
    ): array|object {
        $codigo_tipo_labor = LaboresData::get_codigo_tipo_labor($id_tipo_labor);
        $correlativo_data = LaboresData::get_nuevo_correlativo(
            $id_mina,
            $id_empresa,
            $id_tipo_labor,
            $codigo_tipo_labor
        );
        $id_labor = LaboresData::crear_labor(
            id_mina: $id_mina,
            id_empresa: $id_empresa,
            id_tipo_labor: $id_tipo_labor,
            nombre: $nombre,
            correlativo: $correlativo_data["correlativo"],
            numero_correlativo: $correlativo_data["numero_correlativo"],
            descripcion: $descripcion,
            tipo_sostenimiento: $tipo_sostenimiento,
            veta: $veta,
            ancho: $ancho,
            alto: $alto,
            nivel: $nivel,
            fecha_inicio: $fecha_inicio,
            fecha_fin_estimada: $fecha_fin_estimada
        );

        $creada = LaboresData::get_labor_by_id($id_labor);

        return ApiResponse::success($creada, 'Labor registrada correctamente');
    }

    public static function finalizar_labor(int $id_labor, string $fecha_cierre): array|object
    {
        LaboresData::finalizar_labor($id_labor, $fecha_cierre);
        $actualizada = LaboresData::get_labor_by_id($id_labor);

        return ApiResponse::success($actualizada, 'Labor finalizada correctamente');
    }
}
