<?php

namespace App\Modules\Cotizaciones\Data;

use App\Models\Comparativo;
use App\Models\ComparativoDetalle;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class CotizacionesData
{
    /**
     * Obtener el siguiente número correlativo usando el helper
     */
    public static function get_nuevo_correlativo(): array
    {
        return CorrelativoHelper::generar(
            tabla: 'cotizacion',
            prefijo: 'CTZ',
            columnaFecha: 'fecha_hora_cotizacion'
        );
    }

    /**
     * Crear el registro maestro del comparativo
     */
    public static function crear_comparativo(string $fecha_ahora): int
    {
        $comp = Comparativo::create([
            'created_at' => $fecha_ahora
        ]);
        return $comp->id;
    }

    /**
     * Crear el detalle de productos del comparativo
     */
    public static function crear_comparativo_detalle(int $id_comparativo, int $id_producto, ?int $id_solicitud_detalle = null): int
    {
        $det = ComparativoDetalle::create([
            'id_comparativo' => $id_comparativo,
            'id_producto' => $id_producto,
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle
        ]);
        return $det->id;
    }

    /**
     * Crear cabecera de cotización
     */
    public static function crear_cotizacion(array $data): int
    {
        $cot = Cotizacion::create($data);
        return $cot->id;
    }

    /**
     * Crear detalle de cotización
     */
    public static function crear_cotizacion_detalle(array $data): void
    {
        CotizacionDetalle::create($data);
    }

    /**
     * Asignar empresas a una cotización (Tabla intermedia)
     */
    public static function asignar_empresas_cotizacion(int $id_cotizacion, array $empresas_ids): void
    {
        $inserts = [];
        foreach ($empresas_ids as $id_emp) {
            $inserts[] = [
                'id_cotizacion' => $id_cotizacion,
                'id_empresa'    => (int)$id_emp,
            ];
        }
        
        if (!empty($inserts)) {
            DB::table('empresa_cotizacion')->insert($inserts);
        }
    }

    /**
     * Obtener listado de cotizaciones agrupadas por comparativo
     */
    public static function get_listado_agrupado(): array
    {
        // 1. Cabeceras de comparativos y cotizaciones
        $cotizaciones = DB::select("
            SELECT 
                c.*,
                p.razon_social as proveedor_nombre,
                comp.created_at as comparativo_fecha
            FROM cotizacion c
            INNER JOIN proveedor p ON c.id_proveedor = p.id
            INNER JOIN comparativo comp ON c.id_comparativo = comp.id
            ORDER BY c.id_comparativo DESC, c.id DESC
        ");

        // 2. Empresas vinculadas a las cotizaciones
        // Lo traemos por separado para que el front las asocie por id_cotizacion sin duplicar filas base
        $cotizacionesIds = array_column($cotizaciones, 'id');
        $empresas = [];
        
        if (!empty($cotizacionesIds)) {
            $ids_str = implode(',', $cotizacionesIds);
            $empresas = DB::select("
                SELECT ec.id_cotizacion, e.id as id_empresa, e.razon_social
                FROM empresa_cotizacion ec
                INNER JOIN empresa e ON ec.id_empresa = e.id
                WHERE ec.id_cotizacion IN ($ids_str)
                ORDER BY e.razon_social ASC
            ");
        }

        // 3. Detalles de cada cotización (con nombre del producto y unidad)
        $detalles = DB::select("
            SELECT
                cd.*,
                pr.nombre as producto_nombre,
                pr.id as id_producto,
                um.abreviatura as unidad_medida_abv,
                COALESCE(umb.abreviatura, '---') as unidad_medida_base_abv
            FROM cotizacion_detalle cd
            INNER JOIN comparativo_detalle cpd ON cd.id_comparativo_detalle = cpd.id
            INNER JOIN producto pr ON cpd.id_producto = pr.id
            INNER JOIN unidad_medida um ON cd.id_unidad_medida = um.id
            LEFT JOIN unidad_medida umb ON pr.id_unidad_medida_base = umb.id
            ORDER BY cd.id_cotizacion, pr.nombre ASC
        ");

        return [
            'cotizaciones' => $cotizaciones,
            'empresas'     => $empresas,
            'detalles'     => $detalles,
        ];
    }
}
