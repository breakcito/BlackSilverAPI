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
     * Obtener el siguiente número correlativo
     */
    public static function get_nuevo_correlativo(): array
    {
        return Comparativo::get_nuevo_correlativo();
    }

    /**
     * Crear el registro maestro del comparativo
     */
    public static function crear_comparativo(int $numero_correlativo): int
    {
        return Comparativo::crear_comparativo($numero_correlativo);
    }

    public static function get_comparativos(
        ?int $id_comparativo = null,
        ?int $mes = null,
        ?int $yearcito = null
    ) {
        // Subconsulta que devuelve los correlativos UNICOS de las solicitudes
        // de reabastecimiento que originaron las cotizaciones de este comparativo.
        // Se concatenan con ", " para mostrar en una sola linea (ej. "SCR-26-00005, SCR-26-00003").
        // Si el comparativo NO vino de ninguna solicitud (cotizaciones tradicionales),
        // devuelve NULL.
        $sql = "
        SELECT
            cmp.id AS id_comparativo,
            cmp.numero_correlativo,
            cmp.created_at,
            (
                SELECT GROUP_CONCAT(
                    DISTINCT sr.correlativo
                    ORDER BY sr.correlativo ASC
                    SEPARATOR ', '
                )
                FROM cotizacion ct
                INNER JOIN solicitud_reabastecimiento sr
                    ON sr.id = ct.id_solicitud_reabastecimiento
                WHERE ct.id_comparativo = cmp.id
                  AND ct.id_solicitud_reabastecimiento IS NOT NULL
            ) AS solicitudes_origen_correlativos
        FROM
            comparativo cmp
        WHERE
            1 = 1
        ";

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
