<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Almacen extends Model
{
    protected $table = 'almacen';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_principal',
        'estado',
    ];

    /**
     * obtener la lista simple de almacenes activos
     */
    public static function get_almacenes(?int $id_responsable = null)
    {
        $query = DB::table('almacen as alm')
            ->select('alm.id as id_almacen', 'alm.nombre')
            ->where('alm.estado', 'Activo')
            ->where('alm.es_principal', '!=', 1)
            ->distinct();

        // si recibimos el id del responsable
        if (!is_null($id_responsable)) {
            $query->join('responsable_almacen as res', 'res.id_almacen', '=', 'alm.id')
                ->where('res.estado', 'Activo')
                ->where('res.id_empleado', $id_responsable);
        }

        return $query->get()->toArray();
    }
}
