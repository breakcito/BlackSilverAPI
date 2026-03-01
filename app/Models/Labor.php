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
        'fecha_fin',
        //
        'created_at',
        'estado',
    ];

    // obtener labores, opcionalmente filtrar por mina
    public static function get_labores(?int $id_mina = null)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa,
            l.id_mina,
            l.id_tipo_labor,
            m.nombre as mina,
            e.nombre_comercial as empresa,
            tl.nombre as tipo_labor_nombre,
            tl.es_de_produccion,
            l.correlativo,
            l.nombre,
            l.descripcion,
            l.tipo_sostenimiento,
            l.veta,
            l.ancho,
            l.alto,
            l.nivel,
            l.fecha_fin,
            l.created_at,
            l.estado
        FROM
            labor l
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN empresa e ON e.id = l.id_empresa
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        ';

        $params = [];

        if ($id_mina) {
            $sql .= ' WHERE l.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY l.codigo_correlativo ASC';

        return DB::select($sql, $params);
    }

    public static function get_labor_by_id(int $id)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa,
            l.id_mina,
            l.id_tipo_labor,
            m.nombre as mina,
            e.nombre_comercial as empresa,
            tl.nombre as tipo_labor_nombre,
            tl.es_de_produccion,
            l.correlativo,
            l.nombre,
            l.descripcion,
            l.tipo_sostenimiento,
            l.veta,
            l.ancho,
            l.alto,
            l.nivel,
            l.fecha_fin,
            l.created_at,
            l.estado
        FROM
            labor l
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN empresa e ON e.id = l.id_empresa
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        WHERE
            l.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }
}
