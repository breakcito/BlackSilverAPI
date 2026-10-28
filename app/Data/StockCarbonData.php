<?php

namespace App\Data;

use App\Models\StockCarbon;
use App\Shared\Enums\Carbon\TipoCalidadCarbon;
use Illuminate\Support\Facades\DB;

/**
 * Catalogo global de stock de carbon.
 *
 * El stock se lleva por (almacen, tipo de carbon, calidad). Este catalogo
 * alimenta el autocompletado de la cantidad estimada al despachar una venta.
 */
class StockCarbonData
{
    /**
     * Lista el stock de carbon con filtros.
     * @param array{id_almacen?: int, id_tipo_carbon?: int, calidad?: string, filtros?: string} $opts
     * @return array<object>
     */
    public static function get_stocks(array $opts = []): array
    {
        $sql = '
            SELECT
                s.id AS id_stock_carbon,
                s.id_almacen,
                alm.nombre AS almacen_nombre,
                s.id_tipo_carbon,
                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,
                s.calidad,
                s.stock_actual
            FROM stock_carbon s
            INNER JOIN almacen alm ON alm.id = s.id_almacen
            INNER JOIN tipo_carbon tc ON tc.id = s.id_tipo_carbon
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_almacen'])) {
            $sql .= ' AND s.id_almacen = :id_almacen';
            $params['id_almacen'] = (int) $opts['id_almacen'];
        }

        if (!empty($opts['id_tipo_carbon'])) {
            $sql .= ' AND s.id_tipo_carbon = :id_tipo_carbon';
            $params['id_tipo_carbon'] = (int) $opts['id_tipo_carbon'];
        }

        if (!empty($opts['calidad'])) {
            $sql .= ' AND s.calidad = :calidad';
            $params['calidad'] = (string) $opts['calidad'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (tc.nombre LIKE :q OR tc.codigo LIKE :q OR alm.nombre LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        $sql .= ' ORDER BY tc.nombre ASC, alm.nombre ASC, s.calidad ASC';

        return DB::select($sql, $params);
    }

    /**
     * Detecta las filas que faltan para completar la matriz de stock:
     * por cada almacen de carbon y cada tipo de carbon deben existir las
     * tres calidades (Baja, Intermedia, Alta), aunque su stock sea 0.
     *
     * @return array<int, array{id_almacen: int, id_tipo_carbon: int, calidad: string, stock_actual: int, cambios_log: null}>
     */
    public static function detectar_faltantes(): array
    {
        $almacenes = DB::table('almacen')
            ->where('para_carbon', 1)
            ->where('estado', 'Activo')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $tipos = DB::table('tipo_carbon')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($almacenes) || empty($tipos)) {
            return [];
        }

        $existentes = [];
        foreach (DB::table('stock_carbon')->select('id_almacen', 'id_tipo_carbon', 'calidad')->get() as $fila) {
            $existentes[self::clave_stock((int) $fila->id_almacen, (int) $fila->id_tipo_carbon, (string) ($fila->calidad ?? ''))] = true;
        }

        $faltantes = [];
        foreach ($almacenes as $idAlmacen) {
            foreach ($tipos as $idTipo) {
                foreach (TipoCalidadCarbon::cases() as $calidad) {
                    $clave = self::clave_stock($idAlmacen, $idTipo, $calidad->value);
                    if (isset($existentes[$clave])) {
                        continue;
                    }
                    $existentes[$clave] = true;
                    $faltantes[] = [
                        'id_almacen' => $idAlmacen,
                        'id_tipo_carbon' => $idTipo,
                        'calidad' => $calidad->value,
                        'stock_actual' => 0,
                        'cambios_log' => null,
                    ];
                }
            }
        }

        return $faltantes;
    }

    /**
     * Inserta las filas de stock faltantes de la matriz.
     *
     * @param array<int, array<string, mixed>> $filas
     */
    public static function insertar_filas(array $filas): void
    {
        foreach (array_chunk($filas, 200) as $lote) {
            StockCarbon::insert($lote);
        }
    }

    /**
     * Bloquea las filas padre (tipos de carbon y almacenes de carbon) para
     * que dos peticiones simultaneas no construyan la misma combinacion.
     */
    public static function bloquear_padres(): void
    {
        DB::table('tipo_carbon')->select('id')->lockForUpdate()->get();
        DB::table('almacen')->where('para_carbon', 1)->select('id')->lockForUpdate()->get();
    }

    private static function clave_stock(int $idAlmacen, int $idTipoCarbon, string $calidad): string
    {
        return $idAlmacen . '|' . $idTipoCarbon . '|' . $calidad;
    }
}
