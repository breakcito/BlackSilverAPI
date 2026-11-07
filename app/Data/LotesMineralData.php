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

            lt.id_contratista,
            CONCAT(ctr.nombre, " ", ctr.apellido) as contratista,

            lt.codigo,
            lt.fecha_inicio_produccion,
            lt.descripcion,

            -- sacos acumulados del lote (se recalcula desde los controles de uso)
            lt.cantidad_sacos
        FROM lote_mineral lt
        INNER JOIN mina mna on mna.id = lt.id_mina
        INNER JOIN labor lb on lb.id = lt.id_labor
        INNER JOIN empleado ctr on ctr.id = lt.id_contratista
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

        $sql .= ' ORDER BY lt.codigo ASC';
        return DB::select($sql, $params);
    }

    /**
     * Sumar (o restar) sacos al acumulado de un lote de mineral.
     *
     * El delta se aplica sobre el valor actual de `lote_mineral.cantidad_sacos`.
     * Los deltas provienen de los controles de uso: alta (+), edicion (diferencia
     * entre el valor nuevo y el anterior) y anulacion (-).
     */
    public static function sumar_sacos(int $id_lote_mineral, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        // sprintf con %d: el delta entra como entero literal, sin
        // placeholders (un binding en un Expression no se enlaza en
        // un UPDATE y desalinearia los placeholders).
        $operador = $delta >= 0 ? '+' : '-';

        DB::table('lote_mineral')
            ->where('id', $id_lote_mineral)
            ->update([
                'cantidad_sacos' => DB::raw(
                    sprintf('COALESCE(cantidad_sacos, 0) %s %d', $operador, abs($delta))
                ),
            ]);
    }
}
