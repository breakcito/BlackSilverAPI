<?php

namespace App\Modules\Personal\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cargo extends Model
{
    /**
     * Listar todos los cargos activos.
     */
    public static function get_cargos()
    {
        $sql = '
        SELECT
            c.id AS id_cargo,
            c.nombre,
            c.estado
        FROM
            cargo c
        WHERE
            c.estado = :estado
        ORDER BY c.nombre ASC
        ';

        return DB::select($sql, ['estado' => EstadoBase::Activo->value]);
    }
}
