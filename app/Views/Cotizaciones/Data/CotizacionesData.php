<?php

namespace App\Views\Cotizaciones\Data;

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
     * Obtener listado de cotizaciones agrupadas por comparativo
     */
    public static function get_listado_agrupado(): array
    {
        return DB::select("
            SELECT 
                c.*, 
                p.nombre as proveedor_nombre,
                comp.created_at as comparativo_fecha
            FROM cotizacion c
            INNER JOIN proveedor p ON c.id_proveedor = p.id
            INNER JOIN comparativo comp ON c.id_comparativo = comp.id
            ORDER BY c.id_comparativo DESC, c.id DESC
        ");
    }
}
