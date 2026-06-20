<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOrdenCompra;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompra extends Model
{
    protected $table = 'orden_compra';

    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion', // Por si viene de una cotizacion
        'id_empresa', // Una de las empresas involucradas en la cotizacion
        'id_proveedor',
        'id_empleado_registro',
        //
        'correlativo',
        'numero_correlativo',
        //
        'observacion',
        'fecha_hora_orden',
        //
        'metodo_pago', // Contado / credito
        'fecha_vencimiento_pago', // Solo cuando es a credito
        'moneda', // soles o dolares
        'tipo_cambio_aplicado',
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
        'created_at',
        'estado', // Generada / En Recepcion / Anulada / Cerrada / Completada
    ];

    // Helper que calcula el siguiente correlativo — reseteo anual por fecha_hora_orden
    public static function get_nuevo_correlativo(): array
    {
        return CorrelativoHelper::generar(
            tabla: 'orden_compra',
            prefijo: 'OC',
            columnaFecha: 'fecha_hora_orden'
        );
    }

    // Crea la cabecera de la OC y retorna su ID
    public static function crear_orden(
        int $id_cotizacion,
        int $id_empresa,
        int $id_proveedor,
        int $id_empleado_registro,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_orden,
        string $moneda,
        ?float $tipo_cambio_aplicado,
        bool $es_auditable,
        string $metodo_pago,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $costo_flete,
        float $otros_gastos,
        float $total_antes_igv,
        float $total_despues_igv,
        ?string $observacion = null,
        ?string $fecha_vencimiento_pago = null,
    ): int {
        return self::insertGetId([
            'id_cotizacion' => $id_cotizacion,
            'id_empresa' => $id_empresa,
            'id_proveedor' => $id_proveedor,
            'id_empleado_registro' => $id_empleado_registro,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'fecha_hora_orden' => $fecha_hora_orden,
            'moneda' => $moneda,
            'tipo_cambio_aplicado' => $tipo_cambio_aplicado,
            'es_auditable' => $es_auditable,
            'metodo_pago' => $metodo_pago,
            'fecha_vencimiento_pago' => $fecha_vencimiento_pago,
            'incluye_igv' => $incluye_igv,
            'porcentaje_igv' => $porcentaje_igv,
            'monto_igv' => $monto_igv,
            'costo_flete' => $costo_flete,
            'otros_gastos' => $otros_gastos,
            'total_antes_igv' => $total_antes_igv,
            'total_despues_igv' => $total_despues_igv,
            'created_at' => now(),
            'estado' => EstadoOrdenCompra::Generada->value,
        ]);
    }

    /**
     * Lista las órdenes de compra con datos de empresa y cotización
     */
    public static function get_ordenes(
        ?int $id_orden = null,
        ?int $mes = null,
        ?int $year = null
    ): array|object|null {
        $sql = '
        SELECT
            oc.id as id_orden_compra,
            oc.correlativo,
            -- 
            oc.id_cotizacion,
            cot.correlativo AS correlativo_cotizacion,
            -- 
            oc.id_empresa,
            emp.razon_social AS empresa,
            emp.ruc	AS empresa_ruc,
            emp.path_logo AS empresa_logo,
            -- 
            oc.id_proveedor,
            prov.razon_social AS proveedor,
            prov.tipo_entidad as tipo_entidad_proveedor,
            IFNULL(prov.ruc, prov.dni) AS documento_proveedor,
            -- 
            oc.observacion,
            oc.fecha_hora_orden,
            -- 
            oc.metodo_pago,
            oc.fecha_vencimiento_pago,
            oc.moneda,
            oc.tipo_cambio_aplicado,
            oc.es_auditable,
            -- 
            oc.costo_flete,
            oc.otros_gastos,
            -- 
            oc.total_antes_igv,
            oc.incluye_igv,
            oc.porcentaje_igv,
            oc.monto_igv,
            oc.total_despues_igv,
            -- 
            oc.id_empleado_registro,
            CONCAT(emp_reg.nombre, \' \', emp_reg.apellido) as empleado_registro,
            car_reg.nombre as cargo_empleado_registro,
            -- 
            oc.created_at,
            oc.estado
        FROM orden_compra oc
        LEFT JOIN cotizacion  cot ON cot.id = oc.id_cotizacion
        INNER JOIN empresa emp ON emp.id = oc.id_empresa
        INNER JOIN proveedor prov on prov.id = oc.id_proveedor
        LEFT JOIN empleado emp_reg on emp_reg.id = oc.id_empleado_registro
        LEFT JOIN cargo car_reg on car_reg.id = emp_reg.id_cargo
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_orden !== null) {
            $sql .= ' AND oc.id = :id_orden';
            $params['id_orden'] = $id_orden;
            $result = DB::selectOne($sql, $params);
            if ($result && $result->empresa_logo) {
                $result->empresa_logo = self::logo_a_base64($result->empresa_logo);
            }
            return $result;
        }

        if ($mes !== null) {
            $sql .= ' AND MONTH(oc.fecha_hora_orden) = :mes';
            $params['mes'] = $mes;
        }

        if ($year !== null) {
            $sql .= ' AND YEAR(oc.fecha_hora_orden) = :year';
            $params['year'] = $year;
        }

        $sql .= '
        ORDER BY
            CASE oc.estado
                WHEN "Generada"      THEN 1
                WHEN "En Recepción"  THEN 2
                WHEN "Cerrada"       THEN 3
                WHEN "Completada"    THEN 4
                WHEN "Anulada"       THEN 5
                ELSE 6
            END ASC,
            oc.created_at DESC
        ';

        $results = DB::select($sql, $params);
        foreach ($results as $oc) {
            if ($oc->empresa_logo) {
                $oc->empresa_logo = self::logo_a_base64($oc->empresa_logo);
            }
        }
        return $results;
    }

    /**
     * Convierte un path_logo (relativo o URL completa) a data URL base64.
     */
    private static function logo_a_base64(string $logo): ?string
    {
        if (str_starts_with($logo, 'http')) {
            $parsed      = parse_url($logo, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', $parsed ?? ''), '/');
        } else {
            $relativePath = ltrim($logo, '/');
        }

        $fullPath = storage_path('app/public/' . $relativePath);
        if (!file_exists($fullPath)) return null;

        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }
}

