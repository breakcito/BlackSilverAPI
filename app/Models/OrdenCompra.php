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
        'id_cotizacion',
        'id_empresa',
        'correlativo',
        'numero_correlativo',
        'observacion',
        'fecha_hora_orden',
        'moneda',
        'incluye_igv',
        'porcentaje_igv',
        'monto_igv',
        'total_antes_igv',
        'total_despues_igv',
        'created_at',
        'estado',
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
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_orden,
        string $moneda,
        bool $incluye_igv,
        float $porcentaje_igv,
        float $monto_igv,
        float $total_antes_igv,
        float $total_despues_igv,
        ?string $observacion = null,
    ): int {
        return self::insertGetId([
            'id_cotizacion'       => $id_cotizacion,
            'id_empresa'          => $id_empresa,
            'correlativo'         => $correlativo,
            'numero_correlativo'  => $numero_correlativo,
            'observacion'         => $observacion,
            'fecha_hora_orden'    => $fecha_hora_orden,
            'moneda'              => $moneda,
            'incluye_igv'         => $incluye_igv,
            'porcentaje_igv'      => $porcentaje_igv,
            'monto_igv'           => $monto_igv,
            'total_antes_igv'     => $total_antes_igv,
            'total_despues_igv'   => $total_despues_igv,
            'created_at'          => now(),
            'estado'              => EstadoOrdenCompra::Generada->value,
        ]);
    }

    /**
     * Lista las órdenes de compra con datos de empresa y cotización
     */
    public static function get_ordenes(?int $id_orden = null): array|object|null
    {
        $sql = '
        SELECT
            oc.id,
            oc.correlativo,
            oc.observacion,
            oc.fecha_hora_orden,
            oc.moneda,
            oc.incluye_igv,
            oc.porcentaje_igv,
            oc.monto_igv,
            oc.total_antes_igv,
            oc.total_despues_igv,
            oc.created_at,
            oc.estado,
            --
            oc.id_cotizacion,
            cot.correlativo AS correlativo_cotizacion,
            --
            oc.id_empresa,
            emp.razon_social AS empresa_nombre,
            emp.ruc          AS empresa_ruc
        FROM orden_compra oc
        INNER JOIN cotizacion  cot ON cot.id = oc.id_cotizacion
        INNER JOIN empresa     emp ON emp.id = oc.id_empresa
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_orden !== null) {
            $sql .= ' AND oc.id = :id_orden';
            $params['id_orden'] = $id_orden;
            return DB::selectOne($sql, $params);
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

        return DB::select($sql, $params);
    }
}
