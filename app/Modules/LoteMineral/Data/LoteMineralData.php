<?php

namespace App\Modules\LoteMineral\Data;

use App\Models\LoteMineral;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Enums\_Generic\Periodo;
use Illuminate\Support\Facades\DB;

class LoteMineralData
{
    /**
     * Listar lotes de mineral
     * segun filtros opcionales.
     */
    public static function get_lotes(
        ?int $id_lote_mineral = null,
        ?int $mes = null,
        ?int $yearcito = null
    ) {
        $sql = '
        SELECT
            lm.id as id_lote_mineral,

            lm.codigo,
            lm.fecha_inicio_produccion,
            lm.descripcion,
            
            m.id as id_mina,
            m.nombre as mina,
            
            l.id as id_labor,
            l.nombre as labor,
            
            c.id as id_contratista,
            CONCAT(c.nombre, " ", COALESCE(c.apellido, "")) as contratista,

            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) as empleado_registro,

            lm.estado,
            lm.created_at
        FROM lote_mineral lm
        INNER JOIN empleado c ON lm.id_contratista = c.id
        INNER JOIN mina m ON lm.id_mina = m.id
        INNER JOIN labor l ON lm.id_labor = l.id
        INNER JOIN empleado e ON lm.id_empleado_registro = e.id
        WHERE 1=1
        ';

        $params = [];

        if ($id_lote_mineral != null) {
            $sql .= ' AND lm.id = :id_lote_mineral';
            $params['id_lote_mineral'] = $id_lote_mineral;

            return DB::selectOne($sql, $params);
        }

        if ($mes != null && $yearcito != null) {
            $sql .= ' AND MONTH(lm.created_at) = :mes';
            $sql .= ' AND YEAR(lm.created_at) = :yearcito';

            $params['mes'] = $mes;
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY lm.fecha_inicio_produccion DESC';

        return DB::select($sql, $params);
    }

    /**
     * Crear un nuevo lote de mineral.
     */
    public static function registrar_lote(
        int $id_contratista,
        int $id_mina,
        int $id_labor,
        int $id_empleado_registro,
        string $codigo,
        ?string $descripcion,
        ?string $fecha_inicio_produccion = null,
        EstadoLoteMineral $estado = EstadoLoteMineral::Pendiente
    ) {
        return LoteMineral::insertGetId([
            'id_contratista' => $id_contratista,
            'id_mina' => $id_mina,
            'id_labor' => $id_labor,
            'id_empleado_registro' => $id_empleado_registro,
            'codigo' => $codigo,
            'fecha_inicio_produccion' => $fecha_inicio_produccion,
            'descripcion' => $descripcion,
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Obtener el prefijo de una labor específica.
     */
    public static function get_prefijo_labor(int $id_labor): ?string
    {
        return DB::table('labor')->where('id', $id_labor)->value('prefijo');
    }
}
