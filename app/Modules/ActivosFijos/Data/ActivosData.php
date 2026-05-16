<?php

namespace App\Modules\ActivosFijos\Data;

use Illuminate\Support\Facades\DB;

class ActivosData
{
    /**
     * Listar u obtener un activo
     */
    public static function get_activos(?int $id_activo = null)
    {
        $sql = '
        SELECT
            act.id as id_activo,
            
            -- datos como producto
            act.id_producto,
            pr.nombre as producto,
            pr.es_auditable,
            
            -- datos de la categoria a la que pertenece
            -- y determinar si el activo sirve como transporte,
            -- si necesita llevar algun control por odometro u
            -- horometro desde el modulo de Uso
            pr.id_categoria,
            cat.nombre as categoria,
            cat.para_transporte,
            cat.control_por_odometro,
            cat.control_por_horometro,
            
            -- de que marca es
            act.id_marca,
            marc.nombre as marca,
            
            -- en que mina se encuentra
            act.id_mina,
            mn.nombre as mina,
            
            -- en que almacen se encuentra
            act.id_almacen,
            alm.nombre as almacen,
            alm.es_principal as en_almacen_principal,
            
            -- 
            -- datos propios del activo
            -- 
            act.codigo, -- puesto por el usuario
            act.correlativo, -- lo genera el sistema
            -- datos que los otorga el fabricante
            act.numero_serie, 
            act.modelo,
            act.yearcito_modelo,
            act.descripcion, -- descripcion interna o del fabricante
            act.especificaciones, -- JSON con una lista de objetos clave-valor para campos personalizados

            act.fecha_hora_ingreso,
            act.created_at,
            act.estado
        FROM activo_fijo act
        INNER JOIN producto pr on pr.id = act.id_producto
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        LEFT JOIN marca marc on marc.id = act.id_marca

        -- ubicacion actual, solo puede estar en uno de los 2
        -- o en ninguno de ellos en caso se de de baja
        LEFT JOIN mina mn on mn.id = act.id_mina
        LEFT JOIN almacen alm on alm.id = act.id_almacen
        WHERE 1=1
        ';

        $params = [];

        if ($id_activo != null) {
            $sql .= ' AND act.id = :id_activo';
            $params['id_activo'] = $id_activo;
            $res = DB::selectOne($sql, $params);
            if ($res) {
                if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                    $res->especificaciones = json_decode($res->especificaciones, true);
                }
            }
            return $res;
        }

        $sql .= ' ORDER BY pr.nombre, act.correlativo DESC';
        $results = DB::select($sql, $params);
        foreach ($results as $res) {
            if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                $res->especificaciones = json_decode($res->especificaciones, true);
            }
        }
        return $results;
    }
}
