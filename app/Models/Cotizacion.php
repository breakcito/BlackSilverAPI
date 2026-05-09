<?php

namespace App\Models;

use App\Shared\Enums\Cotizacion\EstadoCotizacion;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cotizacion extends Model
{
    protected $table = 'cotizacion';

    public $timestamps = false;

    protected $fillable = [
        'id_comparativo',
        'id_proveedor',
        //
        'correlativo',
        'numero_correlativo',
        //
        'observacion',
        'fecha_hora_cotizacion',
        //
        'metodo_pago', // Contado / Credito
        'fecha_vencimiento_pago', // Solo cuando es a credito
        'moneda', // Soles o Dolares
        'tipo_cambio_venta_referencial',
        'es_auditable',
        //
        'costo_flete',
        'otros_gastos',
        // 
        'total_antes_igv',
        'incluye_igv',
        'porcentaje_igv',
        'monto_igv',
        'total_despues_igv',
        //
        'evidencias',
        //
        'created_at',
        'estado',
    ];

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
     * Crear cabecera de cotización
     */
    public static function crear_cotizacion(
        int $id_comparativo,
        int $id_proveedor,
        //
        string $correlativo,
        int $numero_correlativo,
        //
        string $fecha_hora_cotizacion,
        //
        string $metodo_pago,
        string $moneda,
        //
        float $costo_flete,
        float $otros_gastos,
        //
        float $total_antes_igv,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $total_despues_igv,
        //
        bool $es_auditable = false,
        ?float $tipo_cambio_venta_referencial = null,
        ?string $observacion = null,
        ?string $fecha_vencimiento_pago = null,
        ?string $evidencias = null,
        ?EstadoCotizacion $estado = EstadoCotizacion::Generada,
    ): int {
        return self::insertGetId([
            'id_comparativo' => $id_comparativo,
            'id_proveedor' => $id_proveedor,
            //
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            //
            'observacion' => $observacion,
            'fecha_hora_cotizacion' => $fecha_hora_cotizacion,
            //
            'metodo_pago' => $metodo_pago,
            'fecha_vencimiento_pago' => $fecha_vencimiento_pago,
            'moneda' => $moneda,
            'tipo_cambio_venta_referencial' => $tipo_cambio_venta_referencial,
            'es_auditable' => $es_auditable ? 1 : 0,
            //
            'costo_flete' => $costo_flete,
            'otros_gastos' => $otros_gastos,
            //
            'total_antes_igv' => $total_antes_igv,
            'incluye_igv' => $incluye_igv,
            'porcentaje_igv' => $porcentaje_igv,
            'monto_igv' => $monto_igv,
            'total_despues_igv' => $total_despues_igv,
            //
            'evidencias' => $evidencias,
            //
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Actualizar cabecera de cotización
     */
    public static function actualizar_cotizacion(
        int $id,
        int $id_proveedor,
        string $metodo_pago,
        string $moneda,
        float $costo_flete,
        float $otros_gastos,
        float $total_antes_igv,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $total_despues_igv,
        ?float $tipo_cambio_venta_referencial = null,
        ?string $observacion = null,
        ?string $fecha_vencimiento_pago = null,
    ): bool {
        return self::where('id', $id)->update([
            'id_proveedor' => $id_proveedor,
            'metodo_pago' => $metodo_pago,
            'moneda' => $moneda,
            'tipo_cambio_venta_referencial' => $tipo_cambio_venta_referencial,
            'costo_flete' => $costo_flete,
            'otros_gastos' => $otros_gastos,
            'total_antes_igv' => $total_antes_igv,
            'incluye_igv' => $incluye_igv ? 1 : 0,
            'porcentaje_igv' => $porcentaje_igv,
            'monto_igv' => $monto_igv,
            'total_despues_igv' => $total_despues_igv,
            'observacion' => $observacion,
            'fecha_vencimiento_pago' => $fecha_vencimiento_pago,
        ]) >= 0;
    }

    public static function get_cotizaciones(
        null|int|array $ids_comparativos = null,
        ?int $id_cotizacion = null,
    ) {
        $sql = '
        SELECT DISTINCT
            ct.id AS id_cotizacion,
            ct.id_comparativo,
            oc.id as id_orden_compra,
            -- 
            ct.id_proveedor,
            prov.razon_social AS proveedor,
            prov.tipo_entidad AS tipo_entidad_proveedor,
            IFNULL(prov.ruc, prov.dni) AS documento_proveedor,
            -- 
            ct.correlativo,
            -- 
            ct.observacion,
            ct.fecha_hora_cotizacion,
            -- 
            ct.metodo_pago,
            ct.fecha_vencimiento_pago,
            ct.moneda,
            ct.tipo_cambio_venta_referencial,
            ct.es_auditable,
            -- 
            ct.costo_flete,
            ct.otros_gastos,
            -- 
            ct.total_antes_igv,
            ct.incluye_igv,
            ct.porcentaje_igv,
            ct.monto_igv,
            ct.total_despues_igv,
            -- 
            ct.evidencias,
            -- 
            ct.created_at,
            ct.estado
        FROM
            cotizacion ct
        INNER JOIN proveedor prov ON
            prov.id = ct.id_proveedor
		LEFT JOIN orden_compra oc on oc.id_cotizacion = ct.id            
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_cotizacion != null) {
            $sql .= ' AND ct.id = :id_cotizacion';
            $params['id_cotizacion'] = $id_cotizacion;
            return DB::selectOne($sql, $params);
        }

        if ($ids_comparativos != null) {
            if (is_array($ids_comparativos)) {
                // array_values garantiza que los índices no rompan el merge
                $placeholders = implode(',', array_fill(0, count($ids_comparativos), '?'));
                $sql .= " AND ct.id_comparativo IN ({$placeholders})";
                $params = array_merge($params, array_values($ids_comparativos));
            } else {
                $sql .= ' AND ct.id_comparativo = ?';
                $params[] = $ids_comparativos;
            }
        }

        $sql .= ' ORDER BY ct.correlativo DESC ';

        return DB::select($sql, $params);
    }
}
