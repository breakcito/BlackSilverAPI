<?php

namespace App\Data;

use App\Models\Empresa;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class EmpresasData
{
    /**
     * Obtener listado simple de empresas
     */
    public static function get_empresas(
        ?int $id_empresa = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ): array {
        $sql = '
        SELECT
            emp.id AS id_empresa,
            emp.ruc,
            emp.razon_social,
            emp.url_logo
        FROM
            empresa emp
        WHERE 1=1
        ';

        $params = [];

        if ($id_empresa !== null) {
            $sql .= ' AND emp.id = :id_empresa';
            $params['id_empresa'] = $id_empresa;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY razon_social ASC';

        return DB::select($sql, $params);
    }

    /**
     * Metodo para consultar datos dinamicos de uno o varias empresas a la vez
     */
    public static function get_empresa_dinamica_by_id(int|array $id_empresa, array $columnas): ?array
    {
        $esArray = is_array($id_empresa);
        $ids = $esArray ? $id_empresa : [$id_empresa];
        // Forzamos la inclusión del ID con su alias
        if (!in_array('id as id_empresa', $columnas)) {
            $columnas[] = 'id as id_empresa';
        }
        $query = Empresa::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }
}
