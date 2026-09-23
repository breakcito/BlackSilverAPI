<?php

namespace App\Modules\AnticiposProveedor\Data;

use Illuminate\Support\Facades\DB;

class AnticiposProveedorData
{
    /**
     * Decodifica la columna JSON `evidencias`. MySQL la devuelve como
     * string; queremos exponerla como array|null al frontend para que
     * el type `IArchivo[]` cuadre.
     */
    private static function decodificarEvidencias(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Aplana el shape del row a un array asociativo con `evidencias` ya
     * decodificada. Las columnas escalares del JOIN se exponen tal cual
     * vienen de MySQL (objects via stdClass).
     */
    private static function hidratar(object $row): object
    {
        $row->evidencias = self::decodificarEvidencias($row->evidencias ?? null);
        return $row;
    }

    /**
     * Lista los anticipos del proveedor con JOINs a empleados, empresa y
     * cuenta bancaria. Incluye TODOS los registros (anulados tambien),
     * porque la UI muestra los anulados en una vista colapsable.
     *
     * Orden: mas recientes primero.
     *
     * @return array<object>
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $sql = '
            SELECT
                a.id AS id_anticipo,
                a.id_empresa,
                e.razon_social AS empresa_nombre,
                a.id_proveedor,
                a.id_empleado_registro,
                er.nombre AS empleado_registro_nombre,
                er.apellido AS empleado_registro_apellido,
                a.id_empleado_anulacion,
                ea.nombre AS empleado_anulacion_nombre,
                ea.apellido AS empleado_anulacion_apellido,
                a.id_cuenta_bancaria_empresa,
                ce.numero_cuenta AS cuenta_bancaria_numero,
                ce.moneda AS cuenta_bancaria_moneda,
                a.medio_pago,
                a.fecha_hora_pago,
                a.numero_operacion,
                a.saldo_inicial,
                a.saldo_actual,
                a.evidencias,
                a.esta_anulado,
                a.fecha_hora_anulacion,
                a.created_at,
                a.estado
            FROM anticipo_proveedor a
            INNER JOIN empresa e ON e.id = a.id_empresa
            INNER JOIN empleado er ON er.id = a.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = a.id_empleado_anulacion
            LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = a.id_cuenta_bancaria_empresa
            WHERE a.id_proveedor = :id_proveedor
            ORDER BY a.created_at DESC, a.id DESC
        ';

        $rows = DB::select($sql, ['id_proveedor' => $id_proveedor]);
        return array_map(fn($r) => self::hidratar($r), $rows);
    }

    /**
     * Resumen por proveedor: cantidad de anticipos NO anulados y suma de
     * su saldo_actual. Pensado para alimentar `get_proveedores` con el
     * JOIN de metricas.
     *
     * @param int[] $ids_proveedor
     * @return array<int, object>  Cada fila trae id_proveedor, cantidad y suma.
     */
    public static function get_resumen_por_proveedores(array $ids_proveedor): array
    {
        if (empty($ids_proveedor)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids_proveedor as $i => $id) {
            $key = "id_proveedor_$i";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $inClause = implode(',', $placeholders);

        $sql = "
            SELECT
                a.id_proveedor,
                COUNT(*) AS cantidad_anticipos,
                COALESCE(SUM(a.saldo_actual), 0) AS suma_saldo_actual
            FROM anticipo_proveedor a
            WHERE a.id_proveedor IN ($inClause)
              AND IFNULL(a.esta_anulado, 0) = 0
            GROUP BY a.id_proveedor
        ";

        return DB::select($sql, $params);
    }

    /**
     * Devuelve un anticipo por id (o null si no existe). Usado por el
     * service para refrescar el row tras una operacion.
     */
    public static function get_por_id(int $id_anticipo): ?object
    {
        $sql = '
            SELECT
                a.id AS id_anticipo,
                a.id_empresa,
                e.razon_social AS empresa_nombre,
                a.id_proveedor,
                a.id_empleado_registro,
                er.nombre AS empleado_registro_nombre,
                er.apellido AS empleado_registro_apellido,
                a.id_empleado_anulacion,
                ea.nombre AS empleado_anulacion_nombre,
                ea.apellido AS empleado_anulacion_apellido,
                a.id_cuenta_bancaria_empresa,
                ce.numero_cuenta AS cuenta_bancaria_numero,
                ce.moneda AS cuenta_bancaria_moneda,
                a.medio_pago,
                a.fecha_hora_pago,
                a.numero_operacion,
                a.saldo_inicial,
                a.saldo_actual,
                a.evidencias,
                a.esta_anulado,
                a.fecha_hora_anulacion,
                a.created_at,
                a.estado
            FROM anticipo_proveedor a
            INNER JOIN empresa e ON e.id = a.id_empresa
            INNER JOIN empleado er ON er.id = a.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = a.id_empleado_anulacion
            LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = a.id_cuenta_bancaria_empresa
            WHERE a.id = :id
            LIMIT 1
        ';
        $row = DB::selectOne($sql, ['id' => $id_anticipo]);
        return $row ? self::hidratar($row) : null;
    }

    /**
     * Inserta un anticipo. La validacion de reglas de negocio (medio de
     * pago, cuenta obligatoria) vive en el Service; este Data solo
     * persiste.
     *
     * @param array<int, array{url:string,path_relativo:string,nombre_original?:?string,extension?:?string}>|null $evidencias
     *   Lista de IArchivo. Se persiste como JSON.
     */
    public static function insertar(
        int $id_empresa,
        int $id_proveedor,
        int $id_empleado_registro,
        ?int $id_cuenta_bancaria_empresa,
        ?string $medio_pago,
        ?string $fecha_hora_pago,
        ?string $numero_operacion,
        float $saldo_inicial,
        float $saldo_actual,
        ?array $evidencias,
        string $estado
    ): int {
        $evidenciasJson = ($evidencias !== null && count($evidencias) > 0)
            ? json_encode($evidencias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        return DB::table('anticipo_proveedor')->insertGetId([
            'id_empresa' => $id_empresa,
            'id_proveedor' => $id_proveedor,
            'id_empleado_registro' => $id_empleado_registro,
            'id_empleado_anulacion' => null,
            'id_cuenta_bancaria_empresa' => $id_cuenta_bancaria_empresa,
            'medio_pago' => $medio_pago,
            'fecha_hora_pago' => $fecha_hora_pago,
            'numero_operacion' => $numero_operacion,
            'saldo_inicial' => $saldo_inicial,
            'saldo_actual' => $saldo_actual,
            'evidencias' => $evidenciasJson,
            'esta_anulado' => 0,
            'fecha_hora_anulacion' => null,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado,
        ]);
    }

    /**
     * Marca un anticipo como anulado (soft). NO se eliminan filas: el
     * kardex de anticipos es inmutable.
     */
    public static function anular(
        int $id_anticipo,
        int $id_empleado_anulacion
    ): int {
        return DB::table('anticipo_proveedor')
            ->where('id', $id_anticipo)
            ->update([
                'esta_anulado' => 1,
                'estado' => 'Anulado',
                'id_empleado_anulacion' => $id_empleado_anulacion,
                'fecha_hora_anulacion' => now()->toDateTimeString(),
            ]);
    }
}
