<?php

namespace App\Modules\Concesiones;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\_Generic\TipoMineral;
use App\Modules\Concesiones\Data\ConcesionesData;
use App\Modules\Concesiones\Data\ContratosData;

class ConcesionesService
{
    /**
     * Obtener listado de concesiones.
     * Adicionalmente, adjunta el historial de contratos (con sus evidencias decodificadas)
     * a cada concesión, replicando el patrón de EmpresasService.
     */
    public static function get_concesiones()
    {
        $concesiones = ConcesionesData::get_concesiones();

        if (empty($concesiones)) {
            return ApiResponse::success([]);
        }

        // Recopilar ids únicos para una sola consulta batch de contratos
        $ids_concesiones = array_map(fn($c) => (int) $c->id_concesion, $concesiones);
        $contratos = collect(ContratosData::get_contratos(id_concesion: $ids_concesiones));

        foreach ($concesiones as $concesion) {
            $concesion->contratos = $contratos->where('id_concesion', $concesion->id_concesion)->values();
        }

        return ApiResponse::success($concesiones);
    }

    /**
     * Crear una nueva concesión
     */
    public static function crear_concesion(
        string $nombre,
        string $codigo_reinfo,
        ?string $ubigeo,
        string|TipoMineral $tipo_mineral
    ) {
        if (ConcesionesData::existe_nombre($nombre)) {
            return ApiResponse::error('El nombre de la concesión ya existe.');
        }

        // Si viene como Enum, extraemos su valor
        $val_tipo = $tipo_mineral instanceof TipoMineral ? $tipo_mineral->value : $tipo_mineral;

        $id = ConcesionesData::crear_concesion(
            nombre: $nombre,
            codigo_reinfo: $codigo_reinfo,
            ubigeo: $ubigeo,
            tipo_mineral: (string) $val_tipo
        );

        return ApiResponse::success(ConcesionesData::get_concesion_by_id($id), 'Concesión creada con éxito');
    }

    /**
     * Obtener historial de contratos de una concesión
     */
    public static function get_contratos(int $id_concesion)
    {
        $contratos = ContratosData::get_contratos($id_concesion);
        return ApiResponse::success($contratos);
    }

    /**
     * Crear contrato con empresa, opcionalmente con evidencias adjuntas.
     *
     * @param  array|null  $evidencias  Archivos UploadedFile[].
     */
    public static function crear_contrato(
        int $id_concesion,
        int $id_empresa,
        string $fecha_inicio,
        ?string $fecha_fin,
        ?array $evidencias = null
    ) {
        if (ContratosData::verificar_contrato_activo($id_concesion, $id_empresa)) {
            return ApiResponse::error('Esta empresa ya tiene un contrato activo en esta concesión.');
        }

        $id = ContratosData::crear_contrato(
            id_concesion: $id_concesion,
            id_empresa: $id_empresa,
            fecha_inicio: $fecha_inicio,
            fecha_fin: $fecha_fin,
            evidencias: $evidencias
        );

        $nuevo = ContratosData::get_contrato_by_id($id);

        return ApiResponse::success($nuevo, 'Contrato registrado correctamente');
    }

    /**
     * Terminar contrato
     */
    public static function terminar_contrato(int $id_contrato)
    {
        ContratosData::terminar_contrato($id_contrato);
        return ApiResponse::success(null, 'Contrato finalizado correctamente');
    }

    /**
     * Sube y acumula nuevas evidencias a un contrato existente.
     *
     * @param  array  $evidencias  Archivos UploadedFile[] a subir.
     */
    public static function subir_evidencias(int $id_contrato, array $evidencias)
    {
        $contrato = ContratosData::get_contrato_by_id($id_contrato);
        if (empty((array) $contrato)) {
            return ApiResponse::error('Contrato no encontrado.');
        }

        $existentes = is_array($contrato->evidencias ?? null) ? $contrato->evidencias : [];
        $nuevas = ContratosData::guardar_evidencias($evidencias);
        $todas = array_merge($existentes, $nuevas);

        $evidenciasJson = json_encode($todas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ContratosData::actualizar_evidencias($id_contrato, $evidenciasJson);

        return ApiResponse::success($todas, 'Evidencias agregadas correctamente');
    }

    /**
     * Elimina una evidencia específica de un contrato por su path_relativo,
     * removiendo también el archivo físico del disco.
     */
    public static function eliminar_evidencia(int $id_contrato, string $path_relativo)
    {
        $contrato = ContratosData::get_contrato_by_id($id_contrato);
        if (empty((array) $contrato)) {
            return ApiResponse::error('Contrato no encontrado.');
        }

        $existentes = is_array($contrato->evidencias ?? null) ? $contrato->evidencias : [];

        // Eliminar archivo físico
        ArchivoHelper::eliminarArchivo($path_relativo);

        $actualizadas = array_values(array_filter(
            $existentes,
            fn($e) => ($e['path_relativo'] ?? '') !== $path_relativo
        ));

        $evidenciasJson = !empty($actualizadas)
            ? json_encode($actualizadas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        ContratosData::actualizar_evidencias($id_contrato, $evidenciasJson);

        return ApiResponse::success($actualizadas, 'Evidencia eliminada correctamente');
    }
}