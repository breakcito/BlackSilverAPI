<?php

namespace App\Modules\Concesiones\Data;

use App\Models\ContratoConcesion;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratosData
{

    /**
     * Obtener historial de contratos de una concesión o un contrato específico
     */
    public static function get_contratos(?int $id_concesion = null, ?int $id_contrato = null): array|object
    {
        $sql = '
        SELECT
            cc.id AS id_contrato,
            cc.id_empresa,
            e.razon_social,
            e.ruc,
            e.url_logo,
            cc.fecha_inicio,
            cc.fecha_fin,
            cc.estado
        FROM
            contrato_concesion cc
        INNER JOIN empresa e ON e.id = cc.id_empresa
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_contrato) {
            $sql .= ' AND cc.id = :id_contrato';
            $params['id_contrato'] = $id_contrato;
            return DB::selectOne($sql, $params) ?? (object) [];
        }

        if ($id_concesion) {
            $sql .= ' AND cc.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }

        $sql .= ' ORDER BY 
            CASE WHEN cc.estado = "Activo" THEN 1 ELSE 2 END ASC,
            cc.fecha_inicio DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener un contrato por id
     */
    public static function get_contrato_by_id(int $id_contrato): array|object
    {
        return self::get_contratos(id_contrato: $id_contrato);
    }

    /**
     * Crear un nuevo contrato con parámetros explícitos
     */
    public static function crear_contrato(
        int $id_concesion,
        int $id_empresa,
        string $fecha_inicio,
        ?string $fecha_fin
    ): int {
        return ContratoConcesion::insertGetId([
            'id_empresa' => $id_empresa,
            'id_concesion' => $id_concesion,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);
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
