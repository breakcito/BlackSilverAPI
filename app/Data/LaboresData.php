<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class LaboresData
{
    /**
     * Obtener listado de labores bajo filtros específicos
     */
    public static function get_labores(
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?int $id_requerimiento = null,
        ?int $id_contratista_excluyente = null
    ): array {

        $query = DB::table('labor as lb')
            ->select([
                'lb.id as id_labor',
                'lb.id_mina',
                'mna.nombre as mina',
                'tp.nombre as tipo_labor',
                'lb.nombre'
            ])
            ->leftJoin('tipo_labor as tp', 'tp.id', '=', 'lb.id_tipo_labor')
            ->join('mina as mna', 'mna.id', '=', 'lb.id_mina');

        // Filtro por requerimiento: Agrega el JOIN dinámicamente solo si se solicita
        if ($id_requerimiento !== null) {
            $query->join('requerimiento_almacen_labor as ral', 'ral.id_labor', '=', 'lb.id')
                ->where('ral.id_requerimiento', $id_requerimiento);
        }

        // Filtro por labor específica
        if ($id_labor !== null) {
            $query->where('lb.id', $id_labor);
        }

        // Filtro por mina
        if ($id_mina !== null) {
            $query->where('lb.id_mina', $id_mina);
        }

        // Filtro para listar solo las labores disponibles para un contratista
        if ($id_contratista_excluyente !== null) {
            $query->whereNotIn('lb.id', function ($query) use ($id_contratista_excluyente) {
                $query->select('id_labor')
                    ->from('labor_contratista')
                    ->where('id_contratista', $id_contratista_excluyente);
            });
        }

        return $query->orderBy('lb.correlativo', 'ASC')->get()->toArray();
    }
}
