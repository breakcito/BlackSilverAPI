<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class MinasData
{
    /**
     * Obtener la lista simple de minas activas con filtros opcionales.
     * Incluye información de la concesión y permite filtrar por responsable.
     * @return array
     */
    public static function get_minas(
        ?int $id_mina = null,
        ?int $id_concesion = null,
        ?int $id_empleado_responsable = null,
        ?int $id_almacen_abastece = null,
    ) {
        $query = DB::table('mina as mn')
            ->select(
                'mn.id as id_mina',
                'mn.nombre',
                'mn.id_concesion',
                'cns.nombre as concesion'
            )
            ->join('concesion as cns', 'cns.id', '=', 'mn.id_concesion')
            ->where('mn.estado', 'Activo')
            ->distinct();

        // Filtro por ID de mina (retorna un solo registro)
        if ($id_mina !== null) {
            $query->where('mn.id', $id_mina);
            return (array) ($query->first() ?? []);
        }

        // Filtro por concesión
        if ($id_concesion !== null) {
            $query->where('mn.id_concesion', $id_concesion);
        }

        // filtro para listar las minas abastecidas por un almacen
        if($id_almacen_abastece !== null){
            $query->join('almacen_mina as am', 'am.id_mina', '=', 'mn.id')
                ->where('am.id_almacen', $id_almacen_abastece);
        }

        // Filtro por responsable
        if ($id_empleado_responsable !== null) {
            $query->join('responsable_mina as res', 'res.id_mina', '=', 'mn.id')
                ->where('res.id_empleado', $id_empleado_responsable)
                ->where('res.estado', 'Activo');
        }

        return $query->orderBy('mn.nombre', 'asc')
            ->get()
            ->toArray();
    }
}

