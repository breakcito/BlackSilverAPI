<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Comparativo;
use App\Models\ComparativoDetalle;
use Illuminate\Support\Facades\DB;

class ComparativoData
{
    /**
     * -------------------------------------------------------
     * QUERYS PARA LA CABECERA
     * -------------------------------------------------------
     */


    /**
     * Obtener el siguiente número correlativo usando el helper
     */
    public static function get_nuevo_correlativo(): array
    {
        return Comparativo::get_nuevo_correlativo();
    }

    /**
     * Crear el registro maestro del comparativo
     */
    public static function crear_comparativo(): int
    {
        return Comparativo::crear_comparativo();
    }

    public static function get_comparativos(
        ?int $id_comparativo = null,
        ?int $mes = null,
        ?int $yearcito = null
    ) {
        $sql = '
        SELECT
            cmp.id AS id_comparativo,
            cmp.numero_correlativo,
            cmp.created_at
        FROM
            comparativo cmp
        WHERE 
            1 = 1
        ';

        $params = [];

        if ($id_comparativo) {
            $sql .= ' AND cmp.id = :id_comparativo ';
            $params['id_comparativo'] = $id_comparativo;
            return DB::selectOne($sql, $params);
        }

        if ($mes) {
            $sql .= ' AND MONTH(cmp.created_at) = :mes ';
            $params['mes'] = $mes;
        }

        if ($yearcito) {
            $sql .= ' AND YEAR(cmp.created_at) = :yearcito ';
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY cmp.numero_correlativo DESC ';

        return DB::select($sql, $params);
    }


    /**
     * -------------------------------------------------------
     * QUERYS PARA EL DETALLE
     * -------------------------------------------------------
     */


    /**
     * Crear el detalle de productos del comparativo
     */
    public static function crear_comparativo_detalle(
        int $id_comparativo,
        int $id_producto,
        ?int $id_solicitud_detalle = null
    ): int {
        return ComparativoDetalle::crear_detalle(
            id_comparativo: $id_comparativo,
            id_producto: $id_producto,
            id_solicitud_detalle: $id_solicitud_detalle
        );
    }

}
