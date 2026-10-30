<?php

namespace App\Modules\AnticiposCliente\Data;

use Illuminate\Support\Facades\DB;

class AnticiposClienteData
{
    /**
     * Bloque SELECT + FROM + JOINs compartido por `get_por_cliente` y
     * `get_por_id`. Vive en una constante para que las dos consultas no
     * se desincronicen cuando se agrega una columna nueva: el listado y
     * el row de retorno post-insert deben exponer EXACTAMENTE el mismo
     * shape o el frontend recibe campos fantasma en uno de los dos.
     */
    private const SELECT_ANTICIPO = '
            SELECT
                a.id AS id_anticipo,
                a.id_empresa,
                e.razon_social AS empresa_nombre,
                a.id_cliente,
                cl.razon_social AS cliente_nombre,
                a.id_empleado_registro,
                er.nombre AS empleado_registro_nombre,
                er.apellido AS empleado_registro_apellido,
                a.id_empleado_anulacion,
                ea.nombre AS empleado_anulacion_nombre,
                ea.apellido AS empleado_anulacion_apellido,
                a.id_cuenta_bancaria_empresa,
                ce.numero_cuenta AS cuenta_bancaria_numero,
                ce.moneda AS cuenta_bancaria_moneda,
                bc.nombre AS cuenta_bancaria_banco,
                bc.abreviatura AS cuenta_bancaria_banco_abv,
                a.medio_pago,
                a.fecha_hora_pago,
                a.numero_operacion,
                a.codigo_comprobante,
                a.observacion,
                a.saldo_inicial,
                a.saldo_actual,
                a.evidencias,
                a.esta_anulado,
                a.fecha_hora_anulacion,
                a.created_at,
                a.estado
            FROM anticipo_cliente a
            INNER JOIN empresa e ON e.id = a.id_empresa
            INNER JOIN cliente cl ON cl.id = a.id_cliente
            INNER JOIN empleado er ON er.id = a.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = a.id_empleado_anulacion
            LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = a.id_cuenta_bancaria_empresa
            LEFT JOIN banco bc ON bc.id = ce.id_banco
    ';

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
     * Normaliza los TINYINT de MySQL a int. Sin esto el frontend recibe
     * `0`/`1` crudos y `Boolean(...)` queda a cargo de cada componente,
     * que es exactamente el tipo de acoplamiento que este metodo existe
     * para evitar.
     */
    private static function hidratar(object $row): object
    {
        $row->evidencias = self::decodificarEvidencias($row->evidencias ?? null);
        $row->esta_anulado = (int) ($row->esta_anulado ?? 0);
        return $row;
    }

    /**
     * Lista los anticipos recibidos de un cliente. Incluye TODOS los
     * registros (anulados tambien), porque la UI muestra los anulados
     * en un filtro aparte.
     *
     * Orden: mas recientes primero.
     *
     * @return array<object>
     */
    public static function get_por_cliente(int $id_cliente): array
    {
        $sql = self::SELECT_ANTICIPO . '
            WHERE a.id_cliente = :id_cliente
            ORDER BY a.created_at DESC, a.id DESC
        ';

        $rows = DB::select($sql, ['id_cliente' => $id_cliente]);
        return array_map(fn($r) => self::hidratar($r), $rows);
    }

    /**
     * Devuelve un anticipo por id (o null si no existe). Usado por el
     * service para refrescar el row tras una operacion.
     */
    public static function get_por_id(int $id_anticipo): ?object
    {
        $sql = self::SELECT_ANTICIPO . '
            WHERE a.id = :id
            LIMIT 1
        ';
        $row = DB::selectOne($sql, ['id' => $id_anticipo]);
        return $row ? self::hidratar($row) : null;
    }

    /**
     * Verifica que el cliente exista, este activo y pertenezca al flujo de
     * carbon (`para_carbon = 1`). Un anticipo de carbon no puede colgarse
     * de un cliente de logistica: es la unica garantia de que el anticipo
     * queda asociado al cliente correcto.
     */
    public static function get_cliente_carbon(int $id_cliente): ?object
    {
        $sql = '
            SELECT id, razon_social, estado, para_carbon
            FROM cliente
            WHERE id = :id
            LIMIT 1
        ';

        return DB::selectOne($sql, ['id' => $id_cliente]);
    }

    /**
     * Verifica que la empresa exista. La tabla `empresa` no tiene columna
     * `estado`, asi que la validacion es de existencia.
     */
    public static function get_empresa(int $id_empresa): ?object
    {
        $sql = '
            SELECT id, razon_social
            FROM empresa
            WHERE id = :id
            LIMIT 1
        ';

        return DB::selectOne($sql, ['id' => $id_empresa]);
    }

    /**
     * Verifica que la cuenta bancaria exista y pertenezca a la empresa
     * indicada. Sin este cruce un operador podria colgar un anticipo de
     * una cuenta de otra empresa.
     */
    public static function get_cuenta_bancaria_de_empresa(int $id_cuenta_bancaria, int $id_empresa): ?object
    {
        $sql = '
            SELECT id, id_empresa, numero_cuenta, estado
            FROM cuenta_bancaria_empresa
            WHERE id = :id
              AND id_empresa = :id_empresa
            LIMIT 1
        ';

        return DB::selectOne($sql, [
            'id' => $id_cuenta_bancaria,
            'id_empresa' => $id_empresa,
        ]);
    }

    /**
     * Inserta un anticipo. La validacion de reglas de negocio (medio de
     * pago, cuenta obligatoria, cliente de carbon) vive en el Service;
     * este Data solo persiste.
     *
     * `id_cuenta_bancaria_empresa` es la cuenta donde la empresa recibio
     * el dinero. Puede venir null unicamente cuando el medio de pago es
     * Efectivo (no hay cuenta bancaria involucrada).
     *
     * @param array<int, array{url:string,path_relativo:string,nombre_original?:?string,extension?:?string}>|null $evidencias
     *   Lista de IArchivo. Se persiste como JSON.
     */
    public static function insertar(
        int $id_empresa,
        int $id_cliente,
        int $id_empleado_registro,
        ?int $id_cuenta_bancaria_empresa,
        string $medio_pago,
        ?string $fecha_hora_pago,
        ?string $numero_operacion,
        ?string $codigo_comprobante,
        ?string $observacion,
        float $saldo_inicial,
        float $saldo_actual,
        ?array $evidencias,
        string $estado
    ): int {
        $evidenciasJson = ($evidencias !== null && count($evidencias) > 0)
            ? json_encode($evidencias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        return DB::table('anticipo_cliente')->insertGetId([
            'id_empresa' => $id_empresa,
            'id_cliente' => $id_cliente,
            'id_empleado_registro' => $id_empleado_registro,
            'id_empleado_anulacion' => null,
            'id_cuenta_bancaria_empresa' => $id_cuenta_bancaria_empresa,
            'medio_pago' => $medio_pago,
            'fecha_hora_pago' => $fecha_hora_pago,
            'numero_operacion' => $numero_operacion,
            'codigo_comprobante' => $codigo_comprobante,
            'observacion' => $observacion,
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
     * registro de anticipos es inmutable, igual que el de proveedores.
     */
    public static function anular(
        int $id_anticipo,
        int $id_empleado_anulacion
    ): int {
        return DB::table('anticipo_cliente')
            ->where('id', $id_anticipo)
            ->update([
                'esta_anulado' => 1,
                'estado' => 'Anulado',
                'id_empleado_anulacion' => $id_empleado_anulacion,
                'fecha_hora_anulacion' => now()->toDateTimeString(),
            ]);
    }
}
