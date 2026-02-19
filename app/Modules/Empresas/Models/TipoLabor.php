<?php

namespace App\Modules\Empresas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TipoLabor extends Model
{
    protected $table = 'tipo_labor';

    /**
     * Listar tipos de labor.
     */
    public static function get_tipos_labor()
    {
        $sql = '
        SELECT
            id AS id_tipo_labor,
            codigo,
            nombre,
            is_produccion
        FROM
            tipo_labor
        ORDER BY nombre ASC
        ';

        return DB::select($sql);
    }

    public static function get_tipo_labor_by_id(int $id)
    {
        return DB::table('tipo_labor')->where('id', $id)->first();
    }
}
