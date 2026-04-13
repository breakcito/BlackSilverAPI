<?php

namespace App\Modules\MinasLabores\Data;

use App\Models\EmpresaMina;
use Illuminate\Support\Facades\DB;

class EmpresasData
{
    /**
     * Lista de empresas disponibles para ser ejecutoras de una mina
     */
    public static function get_empresas_disponibles(int $id_concesion, int $id_mina)
    {
        $sql = '
        SELECT DISTINCT
            em.id AS id_empresa,
            em.razon_social,
            em.path_logo
        FROM
            empresa em
        INNER JOIN contrato_concesion ctr ON
            ctr.id_empresa = em.id
        WHERE
            ctr.estado = "Activo" AND
            ctr.id_concesion = :id_concesion AND
            -- no listar las empresas que ya son ejecutoras
            em.id NOT IN (
                SELECT
                    emi.id_empresa
                FROM empresa_mina emi
                WHERE emi.id_mina = :id_mina
            )
        ';

        return DB::select($sql, [
            'id_concesion' => $id_concesion,
            'id_mina' => $id_mina,
        ]);
    }

    public static function asignar_empresa(int $id_mina, int $id_empresa)
    {
        return EmpresaMina::insertGetId([
            'id_mina' => $id_mina,
            'id_empresa' => $id_empresa,
        ]);
    }

    /**
     * Lista de empresas ejecutoras actuales
     */
    public static function get_empresas_ejecutoras(?int $id_mina = null, ?int $id_empresa_mina = null)
    {
        $sql = '
        SELECT DISTINCT
            emi.id AS id_empresa_mina,
            em.id AS id_empresa,
            em.razon_social,
            em.ruc,
            em.path_logo
        FROM
            empresa em
        INNER JOIN empresa_mina emi ON
            emi.id_empresa = em.id
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_empresa_mina !== null) {
            $sql .= ' AND emi.id = :id_empresa_mina';
            $params['id_empresa_mina'] = $id_empresa_mina;

            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND emi.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY em.razon_social ASC';

        return DB::select($sql, $params);
    }

    public static function get_empresa_ejecutora_by_id(int $id_empresa_mina)
    {
        return self::get_empresas_ejecutoras(id_empresa_mina: $id_empresa_mina);
    }

    public static function existe_empresa_asignada(int $id_mina, int $id_empresa): bool
    {
        return EmpresaMina::where('id_mina', $id_mina)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }
}
