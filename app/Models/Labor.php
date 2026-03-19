<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    protected $table = 'labor';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_mina',
        'id_tipo_labor',
        //
        'correlativo',
        'numero_correlativo',
        'nombre',
        'descripcion',
        'tipo_sostenimiento',
        'veta',
        'ancho',
        'alto',
        'nivel',
        'fecha_inicio',
        'fecha_fin_estimada',
        'fecha_cierre',
        //
        'created_at',
        'estado',
    ];

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(?int $id_requerimiento = null, ?int $id_enlace = null)
    {
        $sql = '
        SELECT
            lab.id AS id_labor,
            lab.nombre,
            lab.correlativo
        FROM
            labor lab
        INNER JOIN requerimiento_almacen_labor ral ON
            ral.id_labor = lab.id
        WHERE
            1=1
        ';

        $params = [];
        if ($id_enlace !== null) {
            $sql .= ' AND ral.id = :id_enlace';
            $params['id_enlace'] = $id_enlace;

            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento !== null) {
            $sql .= ' AND ral.id_requerimiento = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;
        }

        $sql .= ' ORDER BY lab.nombre ASC';

        return DB::select($sql, $params);
    }
}
