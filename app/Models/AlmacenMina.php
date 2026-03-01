<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tabla que ayuda a identificar:
// - A que minas abastece un almacen
// - Que almacenes abastecen una mina
class AlmacenMina extends Model
{
    protected $table = 'almacen_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',
        'id_mina',
    ];

    // Obtener las minas a las que atiende este almacén
    public static function get_minas_asignadas(int $id_almacen)
    {
        $sql = '
        SELECT
            am.id,
            m.nombre AS mina,
            c.nombre AS concesion
        FROM
            almacen_mina am
        INNER JOIN mina m ON m.id = am.id_mina
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            am.id_almacen = :id_almacen
        ORDER BY m.nombre ASC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
