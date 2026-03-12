<?php

namespace App\Views\LotesProductos\Data;

use App\Models\Almacen;
use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class LotesData
{
    /**
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(int $id_empleado): array
    {
        return Almacen::get_almacenes($id_empleado);
    }

    // Util para determinar de que producto se creara el lote
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.id_unidad_medida_base,
            pr.nombre,
            uni.abreviatura as unidad_medida_base,
            pr.es_perecible,
            pr.es_fiscalizado,
            pr.stock_minimo,
            pr.tiempo_espera_vencimiento,
            pr.periodo_espera_vencimiento,
            pr.dias_espera_vencimiento
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE
            pr.estado = "Activo"
        ORDER BY
            pr.nombre;
        ';

        return DB::select($sql, []);
    }

    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(?int $id_almacen = null, ?int $id_lote = null)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            lp.id_unidad_medida,
            lp.id_almacen,
            p.nombre as producto,
            um_base.abreviatura as unidad_medida_base,
            c.nombre AS categoria,
            um_lote.abreviatura AS unidad_medida,
            lp.descripcion,
            lp.correlativo,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            p.es_perecible,
            p.es_fiscalizado,
            p.stock_minimo,
            p.dias_espera_vencimiento,
            /* Cálculo de días restantes */
            CASE 
                WHEN lp.fecha_vencimiento IS NOT NULL THEN 
                    DATEDIFF(lp.fecha_vencimiento,CURRENT_DATE) 
                ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE
                WHEN p.es_perecible != 1 THEN "N/A"
                WHEN lp.fecha_vencimiento IS NULL THEN "Sin fecha"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN "Vencido"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN "Por vencer"
                ELSE "Vigente"
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN producto p ON
            p.id = lp.id_producto
        LEFT JOIN categoria c ON
            c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON
            um_lote.id = lp.id_unidad_medida
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_lote !== null) {
            $sql .= ' AND lp.id = :id_lote';
            $params['id_lote'] = $id_lote;

            return DB::selectOne($sql, $params);
        }

        if ($id_almacen !== null) {
            $sql .= ' AND lp.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY lp.fecha_hora_ingreso DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return self::get_resumen_lotes(id_lote: $id_lote);
    }

    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LOT',
            filtros: ['id_almacen' => $id_almacen],
            columnaFecha: 'fecha_hora_ingreso'
        );
    }

    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        string $correlativo,
        int $numero_correlativo,
        float $stock_inicial,
        float $contenido_por_presentacion,
        float $stock_actual_base,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento
    ) {
        return LoteProducto::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => $descripcion,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'stock_actual' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => 'Activo',
        ]);
    }

    public static function registrar_log_kardex(int $id_lote, $stock_inicial, $stock_actual_base)
    {
        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote,
            'id_origen' => null,
            'tipo_origen' => OrigenMovimiento::NuevoLote->value,
            'tipo_movimiento' => TipoMovimiento::Ingreso->value,
            'stock_anterior' => null,
            'stock_anterior_base' => null,
            'cantidad_movimiento' => $stock_inicial,
            'cantidad_movimiento_base' => $stock_actual_base,
            'stock_resultante' => $stock_inicial,
            'stock_resultante_base' => $stock_actual_base,
            'descripcion' => 'Ingreso por nuevo lote al almacén',
            'created_at' => now(),
        ]);
    }

    public static function get_lote_simple_by_id(int $id_lote)
    {
        return LoteProducto::where('id', $id_lote)->first()?->toArray();
    }

    public static function update_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base)
    {
        return LoteProducto::where('id', $id_lote)->update([
            'stock_actual' => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    public static function get_abreviatura_unidad_medida(int $id_unidad_medida)
    {
        return DB::table('unidad_medida')->where('id', $id_unidad_medida)->value('abreviatura') ?? '';
    }

    public static function registrar_ajuste_kardex(
        int $id_lote,
        string $tipo_movimiento,
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_lote,
        float $cantidad_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        string $descripcion
    ) {
        return KardexProducto::create([
            'id_lote_producto' => $id_lote,
            'id_origen' => null,
            'tipo_origen' => OrigenMovimiento::AjusteStock->value,
            'tipo_movimiento' => $tipo_movimiento,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_lote,
            'cantidad_movimiento_base' => $cantidad_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener unidades de medida 
     */
    public static function get_unidades_medida()
    {
        return DB::select('
            SELECT id AS id_unidad_medida, nombre, abreviatura
            FROM unidad_medida
            ORDER BY nombre ASC
        ');
    }
}
