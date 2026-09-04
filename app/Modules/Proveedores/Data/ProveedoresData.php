<?php

namespace App\Modules\Proveedores\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProveedoresData
{
    /**
     * Listar o obtener un proveedor.
     *
     * El listado excluye los Inactivos (eliminación lógica). Se usa
     * `IFNULL(pr.estado, 'Activo')` porque la columna `estado` es NULLABLE:
     * con un `pr.estado != 'Inactivo'` pelado, las filas con estado NULL
     * darían NULL en la comparación y desaparecerían del listado.
     *
     * Al pedir un proveedor puntual (`$id_proveedor`) NO se filtra por estado:
     * el flujo de eliminación necesita poder leerlo ya Inactivo para
     * devolverlo al frontend.
     */
    public static function get_proveedores(?int $id_proveedor = null, ?bool $paraCarbon = null)
    {
        $sql = '
        SELECT
            pr.id AS id_proveedor,
            pr.tipo_entidad,
            pr.para_mantenimiento,
            pr.para_transporte,
            pr.para_carbon,
            pr.dni,
            pr.ruc,
            pr.razon_social,
            pr.direccion,
            pr.telefono,
            pr.correo,
            pr.cambios_log,
            pr.estado,
            (
                SELECT
                    COUNT(*)
                FROM
                    cuenta_bancaria_proveedor cn
                WHERE
                    cn.id_proveedor = pr.id AND
                    cn.estado = "Activo"
            ) as cantidad_cuentas_bancarias,
            (
                SELECT
                    COUNT(*)
                FROM
                    proveedor_carbon pc
                WHERE
                    pc.id_proveedor = pr.id
            ) as cantidad_tipos_carbon,
            (
                SELECT
                    COUNT(*)
                FROM
                    lugar_extraccion_carbon le
                WHERE
                    le.id_proveedor = pr.id AND
                    le.estado = "Activo"
            ) as cantidad_lugares_extraccion
        FROM
            proveedor pr
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_proveedor) {
            $sql .= ' AND pr.id = :id_proveedor';
            $params['id_proveedor'] = $id_proveedor;
            return DB::selectOne($sql, $params);
        }

        if ($paraCarbon !== null) {
            $sql .= ' AND pr.para_carbon = :paraCarbon';
            $params['paraCarbon'] = $paraCarbon ? 1 : 0;
        }

        // Eliminación lógica: los Inactivos no se listan.
        $sql .= ' AND IFNULL(pr.estado, :estado_activo_fallback) != :estado_inactivo';
        $params['estado_activo_fallback'] = EstadoBase::Activo->value;
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        $sql .= ' ORDER BY pr.razon_social ASC;';
        return DB::select($sql, $params);
    }

    public static function get_proveedor_by_id(int $id_proveedor)
    {
        return self::get_proveedores(id_proveedor: $id_proveedor);
    }

    /**
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos actualizables de actualizar_proveedor().
     */
    private const PROVEEDOR_CAMBIOS_LABELS = [
        'tipo_entidad' => 'Tipo de Entidad',
        'dni' => 'DNI',
        'ruc' => 'RUC',
        'razon_social' => 'Razón Social',
        'direccion' => 'Dirección',
        'telefono' => 'Teléfono',
        'correo' => 'Correo',
        'para_mantenimiento' => 'Para Mantenimiento',
        'para_transporte' => 'Para Transporte',
        'para_carbon' => 'Para Carbón',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del
     * diff, de modo que `false !== 0` (bool vs tinyint de MySQL) no genere
     * falsos positivos en el historial.
     */
    private const PROVEEDOR_CAMBIOS_TIPOS = [
        'tipo_entidad' => 'string',
        'dni' => 'string',
        'ruc' => 'string',
        'razon_social' => 'string',
        'direccion' => 'string',
        'telefono' => 'string',
        'correo' => 'string',
        'para_mantenimiento' => 'bool',
        'para_transporte' => 'bool',
        'para_carbon' => 'bool',
    ];

    /**
     * Normaliza un valor al tipo canónico del campo para comparaciones fiables.
     */
    private static function normalizarParaComparar(mixed $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            'bool' => ((bool) $valor) ? 1 : 0,
            'int' => (int) $valor,
            'float' => (float) $valor,
            default => (string) $valor,
        };
    }

    /**
     * Actualizar un proveedor existente (logística o carbón).
     *
     * `para_carbon` NO se recibe: define en qué pestaña vive el proveedor y no
     * se edita desde este flujo, así que se preserva tal cual está en BD.
     * Si recibe id_empleado + nombre_empleado, calcula el diff y lo apendea a
     * cambios_log (JSON).
     */
    public static function actualizar_proveedor(
        int $id_proveedor,
        string $tipo_entidad,
        string $razon_social,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $correo = null,
        bool $para_mantenimiento = false,
        bool $para_transporte = false,
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
            'para_mantenimiento' => $para_mantenimiento ? 1 : 0,
            'para_transporte' => $para_transporte ? 1 : 0,
        ];

        $cambiosLog = null;
        if ($id_empleado !== null && $nombre_empleado !== null) {
            $original = self::get_proveedores(id_proveedor: $id_proveedor);
            $cambiosLog = self::calcularDiffCambiosProveedor($original, $nuevoEstado, $id_empleado, $nombre_empleado);
        }

        $updatePayload = $nuevoEstado;
        if ($cambiosLog !== null) {
            $updatePayload['cambios_log'] = json_encode($cambiosLog, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('proveedor')
            ->where('id', $id_proveedor)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Compara el proveedor previo (object) con el nuevo estado (array) y
     * devuelve el array cambios_log listo para persistir (apendea una entrada
     * nueva solo si hay al menos un campo modificado).
     */
    private static function calcularDiffCambiosProveedor(
        $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($original !== null && ! empty($original->cambios_log)) {
            $raw = $original->cambios_log;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $logPrevio = is_array($decoded) ? $decoded : [];
            } elseif (is_array($raw)) {
                $logPrevio = $raw;
            }
        }

        $cambios = [];
        foreach (self::PROVEEDOR_CAMBIOS_LABELS as $campoBd => $label) {
            // `para_carbon` está en los labels para auditar cambios
            // programáticos, pero no llega en $nuevoEstado (no se edita).
            if (! array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevoEstado[$campoBd];

            $tipo = self::PROVEEDOR_CAMBIOS_TIPOS[$campoBd] ?? 'string';
            $anteriorNorm = self::normalizarParaComparar($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarParaComparar($valorNuevo, $tipo);

            if ($anteriorNorm !== $nuevoNorm) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    'valor_anterior' => $anteriorNorm,
                    'valor_nuevo' => $nuevoNorm,
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
     * Desactivar (soft delete) un proveedor cambiando su estado a Inactivo.
     *
     * No se elimina físicamente ni se bloquea por referencias históricas
     * (compra_carbon, orden_compra, cotizacion): dar de baja a un proveedor
     * al que ya se le compró es justamente el caso de uso normal.
     */
    public static function eliminar_proveedor(int $id_proveedor): int
    {
        $affected = DB::table('proveedor')
            ->where('id', $id_proveedor)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);

        return (int) $affected;
    }

    /**
     * Verificar si OTRO proveedor activo ya usa el mismo dni / ruc / razón
     * social, excluyendo el que se está editando.
     *
     * Existe aparte de `App\Data\ProveedoresData::ya_existe()` porque ese
     * método no permite excluirse a sí mismo: al guardar una edición sin
     * cambiar nada, el proveedor colisionaría contra su propio registro.
     */
    public static function existe_duplicado(
        int $excluir_id,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $razon_social = null
    ): bool {
        $dni = ($dni === '') ? null : $dni;
        $ruc = ($ruc === '') ? null : $ruc;
        $razon_social = ($razon_social === '') ? null : $razon_social;

        if ($dni === null && $ruc === null && $razon_social === null) {
            return false;
        }

        return DB::table('proveedor')
            ->where('id', '!=', $excluir_id)
            ->whereRaw('IFNULL(estado, ?) != ?', [
                EstadoBase::Activo->value,
                EstadoBase::Inactivo->value,
            ])
            ->where(function ($q) use ($dni, $ruc, $razon_social) {
                if ($dni !== null) {
                    $q->orWhere('dni', $dni);
                }
                if ($ruc !== null) {
                    $q->orWhere('ruc', $ruc);
                }
                if ($razon_social !== null) {
                    $q->orWhere('razon_social', $razon_social);
                }
            })
            ->exists();
    }

    /** Lista cuentas bancarias de varios proveedores en una sola consulta. */
    public static function get_cuentas_bancarias_por_proveedores(array $ids_proveedor): array
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
            cn.id_proveedor AS id_proveedor,
            cn.id AS id_cuenta_bancaria,
            cn.id_banco,
            bc.nombre AS banco,
            bc.abreviatura AS banco_abv,
            bc.es_nacional,
            cn.moneda,
            cn.numero_cuenta,
            cn.cci,
            cn.es_para_detraccion,
            cn.estado
        FROM cuenta_bancaria_proveedor cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE cn.id_proveedor IN ($inClause)
        ORDER BY cn.id_proveedor, cn.es_para_detraccion DESC, cn.moneda, cn.numero_cuenta
        ";

        return DB::select($sql, $params);
    }

}
