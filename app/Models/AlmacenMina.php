<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    // Obtener los almacenes que atienden a una mina
    public static function get_almacenes_por_mina(int $id_mina)
    {
        $sql = "
            SELECT
                a.id as id_almacen,
                a.nombre,
                a.es_principal
            FROM almacen a
            INNER JOIN almacen_mina am ON am.id_almacen = a.id
            WHERE am.id_mina = :id_mina
              AND a.estado = 'Activo'
        ";

        return DB::select($sql, ['id_mina' => $id_mina]);
    }
}
