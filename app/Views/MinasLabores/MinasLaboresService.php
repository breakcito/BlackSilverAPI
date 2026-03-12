<?php

namespace App\Views\MinasLabores;

use App\Shared\Responses\ApiResponse;
use App\Views\MinasLabores\Data\EmpresasData;
use App\Views\MinasLabores\Data\LaboresData;
use App\Views\MinasLabores\Data\MinasData;
use App\Views\MinasLabores\Data\ResponsablesData;

class MinasLaboresService
{
    // ─── Concesiones ──────────────────────────────────────────────────────────

    public static function get_concesiones_sesion(int $id_usuario): array|object
    {
        $concesiones = MinasData::get_concesiones($id_usuario);

        return ApiResponse::success($concesiones);
    }

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

    // ─── Empresas ejecutoras ──────────────────────────────────────────────────

    public static function get_empresas_ejecutoras(int $id_mina): array|object
    {
        $empresas = EmpresasData::get_empresas_ejecutoras($id_mina);

        return ApiResponse::success($empresas);
    }

    public static function get_empresas_disponibles(int $id_concesion, int $id_mina, int $id_usuario): array|object
    {
        $empresas = EmpresasData::get_empresas_disponibles($id_concesion, $id_mina, $id_usuario);

        return ApiResponse::success($empresas);
    }

    public static function asignar_empresa(int $id_mina, int $id_empresa): array|object
    {
        if (EmpresasData::existe_empresa_asignada($id_mina, $id_empresa)) {
            return ApiResponse::error('La empresa ya está asignada como ejecutora de esta mina.');
        }

        $id_empresa_mina = EmpresasData::asignar_empresa($id_mina, $id_empresa);
        $nueva = EmpresasData::get_empresa_ejecutora_by_id($id_empresa_mina);

        return ApiResponse::success($nueva, 'Empresa asignada correctamente');
    }

    // ─── Responsables ─────────────────────────────────────────────────────────

    public static function get_historial_responsables(int $id_mina): array|object
    {
        $historial = ResponsablesData::get_historial_responsables($id_mina);

        return ApiResponse::success($historial);
    }

    public static function get_empleados_disponibles(int $id_mina, int $id_usuario): array|object
    {
        $empleados = ResponsablesData::get_empleados_disponibles($id_usuario, $id_mina);

        return ApiResponse::success($empleados);
    }

    public static function asignar_responsable(int $id_mina, int $id_empleado, string $fecha_inicio): array|object
    {
        // Cerrar la asignación activa anterior
        ResponsablesData::update_fecha_fin_responsabilidad($id_mina, $fecha_inicio);

        $id_res = ResponsablesData::nuevo_responsable($id_mina, $id_empleado, $fecha_inicio);

        $asignado = ResponsablesData::get_responsable_by_id($id_res);

        return ApiResponse::success($asignado, 'Responsable asignado correctamente');
    }

    // ─── Labores ──────────────────────────────────────────────────────────────

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
        string $nombre,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta,
        ?float $ancho,
        ?float $alto,
        ?string $nivel,
        ?string $fecha_inicio,
        ?string $fecha_fin = null
    ) {
        $codigo_tipo_labor = LaboresData::get_codigo_tipo_labor($id_tipo_labor);
        $correlativo_data = LaboresData::get_nuevo_correlativo($id_mina, $codigo_tipo_labor);
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
            fecha_fin: $fecha_fin
        );

        $creada = LaboresData::get_labor_by_id($id_labor);

        return ApiResponse::success($creada, 'Labor registrada correctamente');
    }
}
