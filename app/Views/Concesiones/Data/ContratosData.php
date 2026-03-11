<?php

namespace App\Views\Concesiones\Data;

use App\Models\ContratoConcesion;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratosData
{
    /**
     * Obtener las empresas asociadas al usuario para crear contratos
     */
    public static function get_empresas(int $id_usuario)
    {
        $sql = '
        SELECT DISTINCT
            emp.id AS id_empresa,
            emp.ruc,
            emp.nombre_comercial,
            emp.razon_social,
            emp.path_logo
        FROM
            empresa emp
        INNER JOIN usuario_empresa usu ON usu.id_empresa = emp.id
        WHERE
            usu.id_usuario = :id_usuario
        ';

        return DB::select($sql, ['id_usuario' => $id_usuario]);
    }

    /**
     * Obtener historial de contratos de una concesión
     */
    public static function get_contratos(int $id_concesion)
    {
        $sql = '
        SELECT
            cc.id AS id_contrato,
            cc.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo,
            cc.fecha_inicio,
            cc.fecha_fin,
            cc.estado
        FROM
            contrato_concesion cc
        INNER JOIN empresa e ON e.id = cc.id_empresa
        WHERE
            cc.id_concesion = :id_concesion
        ORDER BY 
            CASE WHEN cc.estado = "Activo" THEN 1 ELSE 2 END ASC,
            cc.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_concesion' => $id_concesion]);
    }

    /**
     * Crear un nuevo contrato con parámetros explícitos
     */
    public static function crear_contrato(
        int $id_concesion,
        int $id_empresa,
        string $fecha_inicio,
        ?string $fecha_fin
    ) {
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
    public static function terminar_contrato(int $id_contrato)
    {
        return ContratoConcesion::where('id', $id_contrato)
            ->update([
                'estado'    => EstadoBase::Inactivo->value,
                'fecha_fin' => Carbon::today()->toDateString(),
            ]);
    }

    /**
     * Verificar si una empresa ya tiene un contrato activo en la concesión
     */
    public static function verificar_contrato_activo(int $id_concesion, int $id_empresa)
    {
        return ContratoConcesion::where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists();
    }
}
