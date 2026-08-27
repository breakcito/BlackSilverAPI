<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class AlmacenesData
{

    /**
     * obtener la lista simple de almacenes activos con filtros opcionales.
     *
     * Por defecto NO se incluyen los almacenes de carbon (`para_carbon=0`):
     * el front debe pasar explicitamente `incluir_carbon=true` para listarlos.
     * Asi, los selects globales (ej. selects de "almacen" en catalogos) nunca
     * muestran almacenes de carbon salvo que el modulo lo pida.
     *
     * Devuelve ademas los datos de ubicacion (departamento, provincia, distrito
     * y direccion) cuando estan registrados.
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_empleado_responsable = null,
        ?int $es_principal = null,
        bool $incluir_carbon = false
    ) {
        $query = DB::table('almacen as alm')
            ->select(
                'alm.id as id_almacen',
                'alm.nombre',
                'alm.es_principal',
                'alm.para_carbon',
                'alm.direccion',
                'alm.id_departamento',
                'alm.id_provincia',
                'alm.id_distrito',
                'd.nombre as departamento_nombre',
                'p.nombre as provincia_nombre',
                'di.nombre as distrito_nombre',
            )
            ->leftJoin('departamento as d', 'd.id', '=', 'alm.id_departamento')
            ->leftJoin('provincia as p', 'p.id', '=', 'alm.id_provincia')
            ->leftJoin('distrito as di', 'di.id', '=', 'alm.id_distrito')
            ->where('alm.estado', 'Activo')
            // Por defecto ocultamos los almacenes de carbon; el front puede
            // pedirlos explicitamente pasando incluir_carbon=true.
            ->where('alm.para_carbon', $incluir_carbon ? 1 : 0)
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
        if ($id_empleado_responsable !== null) {
            $query->join('responsable_almacen as res', 'res.id_almacen', '=', 'alm.id')
                ->where('res.estado', 'Activo')
                ->where('res.id_empleado', $id_empleado_responsable);
        }

        // Primero ordenamos por es_principal (1 antes que 0) y luego por nombre
        return $query->orderBy('alm.es_principal', 'desc')
            ->orderBy('alm.nombre', 'asc')
            ->get()
            ->toArray();
    }
}
