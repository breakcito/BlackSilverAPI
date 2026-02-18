<?php

namespace App\Modules\Inventario\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnidadMedida extends Model
{
    protected $table = 'unidad_medida';

    public static function get_unidades_medida()
    {
        $sql = '
        SELECT
            id,
            nombre,
            abreviatura,
            estado
        FROM
            unidad_medida
        WHERE
            estado = :estado
        ORDER BY nombre ASC
        ';

        return DB::select($sql, ['estado' => EstadoBase::Activo->value]);
    }
}
