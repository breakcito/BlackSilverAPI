<?php

namespace App\Data;

use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use Illuminate\Support\Facades\DB;

class LotesMineralData
{
    public static function get_lotes(
        ?int $id_lote_mineral = null,
        ?int $id_contratista = null,
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?EstadoLoteMineral $estado = EstadoLoteMineral::EnProduccion
    ) {
        $sql = '
        SELECT 
            lt.id as id_lote_mineral,
            
            lt.id_mina,
            mna.nombre as mina,
            
            lt.id_labor,
            lb.nombre as labor,
            lb.prefijo as labor_prefijo,
            
            lt.correlativo,
            lt.codigo_interno,
            lt.fecha_inicio_produccion
        FROM lote_mineral lt
        INNER JOIN mina mna on mna.id = lt.id_mina
        LEFT JOIN labor lb on lb.id = lt.id_labor
        WHERE 1=1
        ';


        $params = [];
        if ($id_lote_mineral !== null) {
            $sql .= ' AND lt.id = :id_lote_mineral';
            $params['id_lote_mineral'] = $id_lote_mineral;
            return DB::selectOne($sql, $params);
        }
        if ($id_contratista !== null) {
            $sql .= ' AND lt.id_contratista = :id_contratista';
            $params['id_contratista'] = $id_contratista;
        }
        if ($id_mina !== null) {
            $sql .= ' AND lt.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }
        if ($id_labor !== null) {
            $sql .= ' AND lt.id_labor = :id_labor';
            $params['id_labor'] = $id_labor;
        }
        if ($estado !== null) {
            $sql .= ' AND lt.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY lt.correlativo ASC';
        return DB::select($sql, $params);
    }

}
