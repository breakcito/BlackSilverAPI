<?php

namespace App\Views\LotesProductos\Data;

use App\Models\Almacen;
use App\Models\KardexProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(?int $id_empleado = null): array
    {
        return Almacen::get_almacenes(id_responsable: $id_empleado);
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

    /**
     * Consulta para verificar si un usuario puede ver
     */
    public static function puede_ver_almacenes_all(int $id_usuario)
    {
        $sql = '
        SELECT
            1
        FROM
            acceso_usuario acu
        WHERE
            -- acceso para ver todos los almacenes para la vista de lotes
            acu.id_acceso = 1 AND 
            -- verificar si el usuario puede hacer eso
            acu.id_usuario = :id_usuario
        ';

        $result = DB::selectOne($sql, ['id_usuario' => $id_usuario]);

        return $result ? true : false;
    }
}
