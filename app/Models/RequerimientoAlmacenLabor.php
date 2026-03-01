<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Tabla que precisa qué labores estan involucradas en
// un requerimiento de almacen
class RequerimientoAlmacenLabor extends Model
{
    protected $table = 'requerimiento_almacen_labor';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento',
        'id_labor',
    ];

    public static function asociar_labores(int $id_requerimiento, array $id_labores)
    {
        $data = [];
        foreach ($id_labores as $id_labor) {
            $data[] = [
                'id_requerimiento' => $id_requerimiento,
                'id_labor' => $id_labor,
            ];
        }

        if (! empty($data)) {
            self::insert($data);
        }
    }

    public static function get_labores_por_requerimiento(int $id_requerimiento)
    {
        return DB::table('requerimiento_almacen_labor as ral')
            ->join('labor as l', 'l.id', '=', 'ral.id_labor')
            ->where('ral.id_requerimiento', $id_requerimiento)
            ->select('l.id', 'l.nombre')
            ->get();
    }
}
