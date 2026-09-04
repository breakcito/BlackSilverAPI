<?php

namespace App\Modules\Clientes\Data;

use App\Models\Cliente;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ClientesData
{
    /** Obtiene la lista de clientes o uno en específico por su id. */
    public static function get_clientes(?int $id_cliente = null)
    {
        $sql = '
        SELECT
            cl.id AS id_cliente,
            cl.tipo_entidad,
            cl.dni,
            cl.ruc,
            cl.razon_social,
            cl.direccion,
            cl.telefono,
            cl.correo,
            cl.estado,
            cl.created_at,
            cl.cambios_log,
            (SELECT COUNT(*) FROM cuenta_bancaria_cliente cbc WHERE cbc.id_cliente = cl.id) AS cantidad_cuentas_bancarias
        FROM
            cliente cl
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_cliente) {
            $sql .= ' AND cl.id = :id_cliente';
            $params['id_cliente'] = $id_cliente;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY cl.razon_social ASC';
        return DB::select($sql, $params);
    }

    /** Obtiene un cliente por su id. */
    public static function get_cliente_by_id(int $id_cliente)
    {
        return self::get_clientes(id_cliente: $id_cliente);
    }

    /** Inserta un nuevo cliente y retorna su id generado. */
    public static function crear_cliente(
        ?string $tipoEntidad,
        ?string $dni,
        ?string $ruc,
        string $razonSocial,
        ?string $direccion,
        ?string $telefono,
        ?string $correo
    ): int {
        return Cliente::insertGetId([
            'tipo_entidad'      => $tipoEntidad,
            'dni'               => $dni,
            'ruc'               => $ruc,
            'razon_social'      => $razonSocial,
            'direccion'         => $direccion,
            'telefono'          => $telefono,
            'correo'            => $correo,
            'estado'            => 'Activo',
            'created_at'        => now(),
        ]);
    }

    /**
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos actualizables de actualizar_cliente().
     * NOTA: 'estado' NO está aquí — la baja lógica se gestiona por eliminar_cliente().
     */
    private const CLIENTE_CAMBIOS_LABELS = [
        'tipo_entidad' => 'Tipo de Entidad',
        'dni' => 'DNI',
        'ruc' => 'RUC',
        'razon_social' => 'Razón Social',
        'direccion' => 'Dirección',
        'telefono' => 'Teléfono',
        'correo' => 'Correo Electrónico',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del diff,
     * de modo que "" vs null no genere falsos positivos.
     */
    private const CLIENTE_CAMBIOS_TIPOS = [
        'tipo_entidad' => 'string',
        'dni' => 'string',
        'ruc' => 'string',
        'razon_social' => 'string',
        'direccion' => 'string',
        'telefono' => 'string',
        'correo' => 'string',
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     * Trata "" como null para campos string opcionales.
     */
    private static function normalizarParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($tipo === 'string') {
            if ($valor === null) return null;
            $trimmed = trim((string) $valor);
            return $trimmed === '' ? null : $trimmed;
        }
        if ($valor === null) {
            return null;
        }
        return match ($tipo) {
            'int' => (int) $valor,
            'float' => (float) $valor,
            'bool' => ((bool) $valor) ? 1 : 0,
            default => (string) $valor,
        };
    }

    /**
     * Decodifica cambios_log a array. MySQL puede devolverlo como string JSON
     * (cuando se selecciona con DB::select) o como array (algunos drivers).
     */
    private static function decodeCambiosLog(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Actualizar un cliente editable. Stock e identificadores NO son editables
     * aquí: el estado se gestiona por eliminar_cliente() (soft-delete).
     *
     * Si recibe id_empleado + nombre_empleado, calcula el diff entre el cliente
     * previo y el nuevo y lo apendea al array cambios_log (JSON).
     */
    public static function actualizar_cliente(
        int $id_cliente,
        ?string $tipo_entidad,
        ?string $dni,
        ?string $ruc,
        string $razon_social,
        ?string $direccion,
        ?string $telefono,
        ?string $correo,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $nuevoEstado = [
            'tipo_entidad' => $tipo_entidad,
            'dni' => $dni,
            'ruc' => $ruc,
            'razon_social' => $razon_social,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
        ];

        $cambiosLog = null;
        if ($id_empleado !== null && $nombre_empleado !== null) {
            $original = self::get_clientes(id_cliente: $id_cliente);
            $cambiosLog = self::calcularDiffCambiosCliente($original, $nuevoEstado, $id_empleado, $nombre_empleado);
        }

        $updatePayload = $nuevoEstado;
        if ($cambiosLog !== null) {
            $updatePayload['cambios_log'] = json_encode($cambiosLog, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('cliente')
            ->where('id', $id_cliente)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Compara el cliente previo (object|array) con el nuevo estado (array) y devuelve
     * el array de cambios_log listo para persistir. Mantiene el log previo y solo
     * agrega entrada si hay al menos un campo modificado.
     */
    private static function calcularDiffCambiosCliente(
        $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($original !== null) {
            $logPrevio = self::decodeCambiosLog($original->cambios_log ?? null);
        }

        $cambios = [];
        foreach (self::CLIENTE_CAMBIOS_LABELS as $campoBd => $label) {
            if (!array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $tipo = self::CLIENTE_CAMBIOS_TIPOS[$campoBd] ?? 'string';
            $anteriorNorm = self::normalizarParaComparar($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarParaComparar($valorNuevo, $tipo);

            if ($anteriorNorm !== $nuevoNorm) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    'valor_anterior' => $valorAnterior,
                    'valor_nuevo' => $valorNuevo,
                ];
            }
        }

        if (count($cambios) === 0) {
            return $logPrevio;
        }

        $logPrevio[] = [
            'id_empleado' => $id_empleado,
            'nombre_empleado' => $nombre_empleado,
            'motivo' => null,
            'update_at' => now()->toDateTimeString(),
            'cambios' => $cambios,
        ];

        return $logPrevio;
    }

    /**
     * Desactivar (soft delete) un cliente cambiando su estado a Inactivo.
     * Registra la accion en cambios_log para mantener trazabilidad.
     * No se elimina físicamente para preservar la integridad referencial con
     * cuenta_bancaria_cliente y otros módulos relacionados.
     */
    public static function eliminar_cliente(
        int $id_cliente,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $original = self::get_clientes(id_cliente: $id_cliente);

        $logPrevio = [];
        if ($original !== null) {
            $logPrevio = self::decodeCambiosLog($original->cambios_log ?? null);
        }

        if ($id_empleado !== null && $nombre_empleado !== null && $original !== null) {
            $estadoAnterior = $original->estado ?? null;
            if ($estadoAnterior !== EstadoBase::Inactivo->value) {
                $logPrevio[] = [
                    'id_empleado' => $id_empleado,
                    'nombre_empleado' => $nombre_empleado,
                    'motivo' => null,
                    'update_at' => now()->toDateTimeString(),
                    'cambios' => [[
                        'campo_bd' => 'estado',
                        'campo' => 'Estado',
                        'valor_anterior' => $estadoAnterior,
                        'valor_nuevo' => EstadoBase::Inactivo->value,
                    ]],
                ];
            }
        }

        $updatePayload = ['estado' => EstadoBase::Inactivo->value];
        if (count($logPrevio) > 0 && $original !== null) {
            $updatePayload['cambios_log'] = json_encode($logPrevio, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('cliente')
            ->where('id', $id_cliente)
            ->update($updatePayload);

        return (int) $affected;
    }
}
