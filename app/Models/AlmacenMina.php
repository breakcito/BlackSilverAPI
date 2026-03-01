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

    public static function asignar_mina(int $id_almacen, int $id_mina)
    {
        return self::insertGetId([
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina,
        ]);
    }

    public static function verificar_mina_asignada(int $id_almacen, int $id_mina)
    {
        return self::where('id_almacen', $id_almacen)
            ->where('id_mina', $id_mina)
            ->exists();
    }

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

    public static function desasignar_mina(int $id_asignacion)
    {
        return self::where('id', $id_asignacion)->delete();
    }
}
