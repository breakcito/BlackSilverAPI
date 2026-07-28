<?php

namespace App\Modules\Concesiones\Data;

use App\Models\ContratoConcesion;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Helpers\ArchivoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratosData
{

    /**
     * Obtener historial de contratos de una concesión o un contrato específico.
     * Decodifica la columna JSON `evidencias` para exponerla como array al front.
     */
    public static function get_contratos(
        int|array|null $id_concesion = null,
        int|array|null $id_contrato = null
    ): array|object {
        $sql = '
        SELECT
            cc.id AS id_contrato,
            cc.id_empresa,
            cc.id_concesion,
            e.razon_social,
            e.ruc,
            e.url_logo,
            cc.fecha_inicio,
            cc.fecha_fin,
            cc.estado,
            cc.evidencias
        FROM
            contrato_concesion cc
        INNER JOIN empresa e ON e.id = cc.id_empresa
        WHERE
            1 = 1
        ';

        $params = [];

        // Si es un solo ID de contrato (int)
        if (is_int($id_contrato)) {
            $sql .= ' AND cc.id = :id_contrato';
            $params['id_contrato'] = $id_contrato;
            $contrato = DB::selectOne($sql, $params) ?? (object) [];
            if (isset($contrato->evidencias)) {
                $contrato->evidencias = self::decodificar_evidencias($contrato->evidencias);
            }
            return $contrato;
        }

        // Si son varios ID de contrato (array)
        if (is_array($id_contrato) && !empty($id_contrato)) {
            $ids = array_values($id_contrato);
            $in = implode(',', array_map(fn($i) => ":id_c_$i", array_keys($ids)));
            $sql .= " AND cc.id IN ($in)";
            foreach ($ids as $i => $id) {
                $params["id_c_$i"] = $id;
            }
        }

        // Si es un solo ID o un array de concesiones
        if (is_int($id_concesion)) {
            $sql .= ' AND cc.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        } elseif (is_array($id_concesion) && !empty($id_concesion)) {
            $ids = array_values($id_concesion);
            $in = implode(',', array_map(fn($i) => ":id_conc_$i", array_keys($ids)));
            $sql .= " AND cc.id_concesion IN ($in)";
            foreach ($ids as $i => $id) {
                $params["id_conc_$i"] = $id;
            }
        }

        $sql .= ' ORDER BY
            CASE WHEN cc.estado = "Activo" THEN 1 ELSE 2 END ASC,
            cc.fecha_inicio DESC';

        $contratos = DB::select($sql, $params);
        foreach ($contratos as $contrato) {
            $contrato->evidencias = self::decodificar_evidencias($contrato->evidencias);
        }

        return $contratos;
    }

    /**
     * Decodifica el campo JSON `evidencias` a array asociativo.
     *
     * @param  mixed  $valor  String JSON, array ya decodificado o null.
     * @return array  Listado de archivos con estructura IArchivo, o [] si está vacío.
     */
    private static function decodificar_evidencias(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        if (is_string($valor) && $valor !== '') {
            $decoded = json_decode($valor, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Obtener un contrato por id
     */
    public static function get_contrato_by_id(int $id_contrato): array|object
    {
        return self::get_contratos(id_contrato: $id_contrato);
    }

    /**
     * Crea un nuevo contrato. Si se proveen evidencias, primero se persisten en disco.
     *
     * @param  array|null  $evidencias  Archivos UploadedFile[] o null.
     */
    public static function crear_contrato(
        int $id_concesion,
        int $id_empresa,
        string $fecha_inicio,
        ?string $fecha_fin,
        ?array $evidencias = null
    ): int {
        $evidenciasJson = null;
        if (!empty($evidencias)) {
            $guardados = self::guardar_evidencias($evidencias);
            $evidenciasJson = !empty($guardados)
                ? json_encode($guardados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null;
        }

        return ContratoConcesion::insertGetId([
            'id_empresa' => $id_empresa,
            'id_concesion' => $id_concesion,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'evidencias' => $evidenciasJson,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Persiste los archivos de evidencias en disco y retorna el array normalizado
     * con la estructura esperada por el front (url, path_relativo, nombre_original, extension).
     *
     * @param  array  $evidencias  Archivos UploadedFile[].
     */
    public static function guardar_evidencias(array $evidencias): array
    {
        return ArchivoHelper::guardarArchivos('contratos_concesion', $evidencias);
    }

    /**
     * Actualiza el JSON de evidencias acumulado de un contrato existente.
     */
    public static function actualizar_evidencias(int $id_contrato, ?string $evidenciasJson): bool
    {
        return (bool) ContratoConcesion::where('id', $id_contrato)
            ->update(['evidencias' => $evidenciasJson]);
    }

    /**
     * Terminar un contrato (desactivar y registrar fecha fin)
     */
    public static function terminar_contrato(int $id_contrato): int
    {
        return ContratoConcesion::where('id', $id_contrato)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
                'fecha_fin' => Carbon::today()->toDateString(),
            ]);
    }

    /**
     * Verificar si una empresa ya tiene un contrato activo en la concesión
     */
    public static function verificar_contrato_activo(int $id_concesion, int $id_empresa): bool
    {
        return ContratoConcesion::where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists();
    }
}