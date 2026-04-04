<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class AlmacenesData
{

    /**
     * obtener la lista simple de almacenes activos con filtros opcionales
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_responsable = null,
        ?int $es_principal = null
    ) {
        $query = DB::table('almacen as alm')
            ->select('alm.id as id_almacen', 'alm.nombre', 'alm.es_principal')
            ->where('alm.estado', 'Activo')
            ->distinct();

        // filtro por id de almacen
        if ($id_almacen !== null) {
            $query->where('alm.id', $id_almacen);
            return $query->get()->toArray()[0] ?? [];
        }

        // filtro por si es o no principal
        if ($es_principal !== null) {
            $query->where('alm.es_principal', $es_principal);
        }


        // si recibimos el id del responsable
        if ($id_responsable !== null) {
            $query->join('responsable_almacen as res', 'res.id_almacen', '=', 'alm.id')
                ->where('res.estado', 'Activo')
                ->where('res.id_empleado', $id_responsable);
        }

        // Primero ordenamos por es_principal (1 antes que 0) y luego por nombre
        return $query->orderBy('alm.es_principal', 'desc')
            ->orderBy('alm.nombre', 'asc')
            ->get()
            ->toArray();
    }
}
