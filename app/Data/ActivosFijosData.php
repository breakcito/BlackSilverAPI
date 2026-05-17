<?php

namespace App\Data;

use App\Models\ActivoFijo;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class ActivosFijosData
{
    /**
     * Obtiene información dinámica de uno o varios activos fijos.
     * Permite especificar las columnas exactas a consultar mediante un array.
     * @param array $columnas Array de strings con los nombres de las columnas a recuperar.
     * @return array|null Retorna un array con los resultados o null si no se encuentra el registro.
     */
    public static function get_activo_by_id(int|array $id_activo, array $columnas): ?array
    {
        $esArray = is_array($id_activo);
        $ids = $esArray ? $id_activo : [$id_activo];
        // Forzamos la inclusión del ID con su alias
        if (!in_array('id as id_activo', $columnas)) {
            $columnas[] = 'id as id_activo';
        }
        $query = ActivoFijo::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }
        return $query->first()?->toArray();
    }

    /**
     * Obtener solo los activos fijos
     * que esten disponibles segun se requiere.
     */
    public static function get_activos_disponibles(
        ?int $id_activo = null,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_producto = null,
        //
        ?bool $para_transporte = null,
        ?bool $control_por_odometro = null,
        ?bool $control_por_horometro = null,
        ?EstadoActivoFijo $estado = null
    ) {
        $sql = '
        SELECT
            act.id as id_activo,
            act.correlativo, -- lo genera el sistema
            
            -- en que posible almacen se encuentra 
            act.id_almacen,
            alm.nombre as almacen,
            alm.es_principal as en_almacen_principal,
            
            -- en que posible almacen se encuentra 
            act.id_mina,
            mn.nombre as mina,
            
            -- datos como producto
            act.id_producto,
            pr.nombre as producto,
            pr.es_auditable,
            
            cat.para_transporte, -- si el activo es para transporte/vehiculo
            cat.control_por_odometro, -- si el activo requiere tener un control por odometro
            cat.control_por_horometro, -- si el activo requiere tener un control por horometro
            
            -- unidad base para activos -> UNIDAD
            pr.id_unidad_medida_base,
            umb.nombre as unidad_medida_base,
            umb.abreviatura as unidad_medida_base_abv
        FROM activo_fijo act
        INNER JOIN producto pr on pr.id = act.id_producto
        INNER JOIN unidad_medida umb on umb.id = pr.id_unidad_medida_base
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        -- en que posible lugar se encuentra
        LEFT JOIN almacen alm on alm.id = act.id_almacen
        LEFT JOIN mina mn ON mn.id = act.id_mina
        WHERE 1=1
        ';

        $params = [];

        if ($id_activo != null) {
            $sql .= ' AND act.id = :id_activo';
            $params['id_activo'] = $id_activo;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen != null) {
            $sql .= ' AND act.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        if ($id_mina != null) {
            $sql .= ' AND act.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        if ($id_producto != null) {
            $sql .= ' AND act.id_producto = :id_producto';
            $params['id_producto'] = $id_producto;
        }

        if ($para_transporte != null) {
            $sql .= ' AND cat.para_transporte = :para_transporte';
            $params['para_transporte'] = $para_transporte ? 1 : 0;
        }

        if ($control_por_odometro != null) {
            $sql .= ' AND cat.control_por_odometro = :control_por_odometro';
            $params['control_por_odometro'] = $control_por_odometro ? 1 : 0;
        }

        if ($control_por_horometro != null) {
            $sql .= ' AND cat.control_por_horometro = :control_por_horometro';
            $params['control_por_horometro'] = $control_por_horometro ? 1 : 0;
        }

        if ($estado != null) {
            $sql .= ' AND act.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY pr.nombre, act.correlativo DESC';
        return DB::select($sql, $params);
    }

    public static function get_nuevo_correlativo(string $prefijo = 'AF')
    {
        return CorrelativoHelper::generar(
            tabla: 'activo_fijo',
            prefijo: $prefijo,
            longitudCeros: 4,
            reseteo: Periodo::Ninguno,
        );
    }

    /**
     * Crear un nuevo activo fijo 
     */
    public static function crear_activo(
        int $id_producto,
        string $correlativo,
        int $numero_correlativo,
        ?int $id_almacen = null,
        ?int $id_mina = null,
        ?int $id_marca = null,
        //
        ?string $codigo = null,
        ?string $numero_serie = null,
        ?string $modelo = null,
        ?int $yearcito_modelo = null,
        ?string $descripcion = null,
        ?array $especificaciones = null,
        ?string $fecha_hora_ingreso = null,
        ?EstadoActivoFijo $estado = EstadoActivoFijo::EnUso
    ) {
        return ActivoFijo::insertGetId([
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina,
            'id_marca' => $id_marca,
            //
            'codigo' => $codigo,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'numero_serie' => $numero_serie,
            'modelo' => $modelo,
            'yearcito_modelo' => $yearcito_modelo,
            'descripcion' => $descripcion,
            'especificaciones' => $especificaciones ? json_encode($especificaciones) : null,
            //
            'fecha_hora_ingreso' => $fecha_hora_ingreso ?? now(),
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }
}
