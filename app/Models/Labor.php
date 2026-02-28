<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    protected $table = 'labor';

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

    public static function update_labor(
        int $id,
        ?string $nombre = null,
        ?string $descripcion = null,
        ?string $tipo_sostenimiento = null,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null,
        ?string $fecha_fin = null,
        ?string $estado = null
    ) {
        $datos = [
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta'               => $veta,
            'ancho'              => $ancho,
            'alto'               => $alto,
            'nivel'              => $nivel,
            'fecha_fin'          => $fecha_fin,
            'estado'             => $estado
        ];

        // eliminados todos los valores null
        $update = array_filter($datos, fn($valor) => !is_null($valor));

        // solo ejecutamos si hay algo que actualizar
        if (empty($update)) {
            return false;
        }

        return self::where('id', $id)->update($update);
    }

    /**
     * Verificar si el usuario pertenece a la empresa encargada de la labor.
     */
    public static function check_usuario_pertenece_empresa(int $id_usuario, int $id_empresa)
    {
        return self::where('id_usuario', $id_usuario)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }
}
