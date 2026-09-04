<?php

namespace App\Modules\ActivosFijos\Data;

use App\Shared\Enums\ActivoFijo\EstadoActivoFijo;
use Illuminate\Support\Facades\DB;

class ActivosData
{
    /**
     * Listar u obtener un activo
     */
    public static function get_activos(?int $id_activo = null)
    {
        $sql = '
        SELECT
            act.id as id_activo,

            -- datos como producto
            act.id_producto,
            pr.nombre as producto,
            pr.es_auditable,

            -- datos de la categoria a la que pertenece
            -- y determinar si el activo sirve como transporte,
            -- si necesita llevar algun control por odometro u
            -- horometro desde el modulo de Uso
            pr.id_categoria,
            cat.nombre as categoria,
            cat.para_transporte,
            cat.control_por_odometro,
            cat.control_por_horometro,
            cat.control_por_vueltas,

            -- de que marca es
            act.id_marca,
            marc.nombre as marca,

            -- en que mina se encuentra
            act.id_mina,
            mn.nombre as mina,

            -- en que labor se encuentra (opcional)
            act.id_labor,
            lb.nombre as labor,

            -- en que almacen se encuentra
            act.id_almacen,
            alm.nombre as almacen,
            alm.es_principal as en_almacen_principal,

            --
            -- datos propios del activo
            --
            act.codigo, -- puesto por el usuario
            act.correlativo, -- lo genera el sistema
            act.serie_placa,
            act.numero_placa,
            -- datos que los otorga el fabricante
            act.numero_serie,
            act.modelo,
            act.yearcito_modelo,
            act.descripcion, -- descripcion interna o del fabricante
            act.especificaciones, -- JSON con una lista de objetos clave-valor para campos personalizados
            act.evidencias, -- JSON con archivos adjuntos de evidencia

            -- Nuevos campos
            act.id_empleado_responsable,
            CONCAT(emp.nombre, \' \', emp.apellido) as empleado_responsable,
            COALESCE(occ.serie, act.serie_factura_compra) as serie_factura_compra,
            COALESCE(occ.numero, act.numero_factura_compra) as numero_factura_compra,
            act.costo_compra,
            act.costo_promedio_base,
            act.id_orden_compra_recepcion_detalle,
            act.id_orden_compra_detalle,
            ocd.id_orden_compra,
            occr.id_orden_compra_comprobante,

            act.fecha_hora_ingreso,
            act.created_at,
            act.total_horas,
            act.total_kilometros,
            act.total_vueltas,
            act.proxima_advertencia_horas,
            act.proxima_advertencia_kilometros,
            act.proxima_advertencia_vueltas,
            act.intervalo_mantenimiento_horas,
            act.intervalo_mantenimiento_kilometros,
            act.intervalo_mantenimiento_vueltas,
            act.estado,
            act.cambios_log
        FROM activo_fijo act
        INNER JOIN producto pr on pr.id = act.id_producto
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        LEFT JOIN marca marc on marc.id = act.id_marca

        -- ubicacion actual, solo puede estar en uno de los 3
        -- o en ninguno de ellos en caso se de de baja
        LEFT JOIN mina mn on mn.id = act.id_mina
        LEFT JOIN labor lb on lb.id = act.id_labor
        LEFT JOIN almacen alm on alm.id = act.id_almacen
        LEFT JOIN empleado emp on emp.id = act.id_empleado_responsable
        LEFT JOIN orden_compra_detalle ocd on ocd.id = act.id_orden_compra_detalle
        LEFT JOIN orden_compra_recepcion_detalle ocrd on ocrd.id = act.id_orden_compra_recepcion_detalle
        LEFT JOIN orden_compra_comprobante_recepcion occr on occr.id_orden_compra_recepcion = ocrd.id_orden_compra_recepcion
        LEFT JOIN orden_compra_comprobante occ on occ.id = occr.id_orden_compra_comprobante
        WHERE 1=1
        ';

        $params = [];

        if ($id_activo != null) {
            $sql .= ' AND act.id = :id_activo';
            $params['id_activo'] = $id_activo;
            $res = DB::selectOne($sql, $params);
            if ($res) {
                if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                    $res->especificaciones = json_decode($res->especificaciones, true);
                }
                if (isset($res->evidencias) && is_string($res->evidencias)) {
                    $res->evidencias = json_decode($res->evidencias, true);
                }
                // Hidratar las labores abastecidas (solo cuando es detalle)
                $res->labores_abastecidas = self::get_labores_abastecidas((int) $res->id_activo);
            }
            return $res;
        }

        // Filtrar los activos dados de baja (estado equivalente a "Inactivo"
        // en Productos/Lotes/Clientes). Si se requiere verlos, se puede
        // agregar luego un toggle "Mostrar eliminados" en el frontend.
        $sql .= ' AND act.estado != :estado_dado_de_baja';
        $params['estado_dado_de_baja'] = EstadoActivoFijo::DadoDeBaja->value;

        $sql .= ' ORDER BY pr.nombre, act.correlativo DESC';
        $results = DB::select($sql, $params);
        if (!empty($results)) {
            // Obtener todos los id_activo para hidratar en una sola pasada
            $ids = array_map(fn($r) => (int) $r->id_activo, $results);
            $laboresPorActivo = self::get_labores_abastecidas_batch($ids);

            foreach ($results as $res) {
                if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                    $res->especificaciones = json_decode($res->especificaciones, true);
                }
                if (isset($res->evidencias) && is_string($res->evidencias)) {
                    $res->evidencias = json_decode($res->evidencias, true);
                }
                $res->labores_abastecidas = $laboresPorActivo[(int) $res->id_activo] ?? [];
            }
        }
        return $results;
    }

    /**
     * Actualiza los campos editables de un activo fijo (metadata).
     * NO toca ubicación física (eso lo maneja el Service vía new_ubicacion).
     * @param array $data Mapa clave=>valor con los campos a actualizar
     */
    public static function actualizar_activo(int $id_activo, array $data): int
    {
        return DB::table('activo_fijo')
            ->where('id', $id_activo)
            ->update($data);
    }

    /**
     * Devuelve las labores abastecidas por un activo fijo.
     * @return array Lista de {id_labor, nombre}
     */
    public static function get_labores_abastecidas(int $id_activo): array
    {
        $sql = '
        SELECT laa.id_labor, lb.nombre
        FROM labor_abastecida_activo laa
        INNER JOIN labor lb ON lb.id = laa.id_labor
        WHERE laa.id_activo_fijo = :id_activo
        ORDER BY lb.nombre ASC
        ';
        return DB::select($sql, ['id_activo' => $id_activo]);
    }

    /**
     * Variante batch para evitar N+1 al listar.
     * @return array<int, array> Map id_activo => [ {id_labor, nombre} ]
     */
    public static function get_labores_abastecidas_batch(array $ids_activo): array
    {
        if (empty($ids_activo)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids_activo), '?'));
        $sql = "
        SELECT laa.id_activo_fijo, laa.id_labor, lb.nombre
        FROM labor_abastecida_activo laa
        INNER JOIN labor lb ON lb.id = laa.id_labor
        WHERE laa.id_activo_fijo IN ($placeholders)
        ORDER BY lb.nombre ASC
        ";
        $rows = DB::select($sql, $ids_activo);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->id_activo_fijo][] = [
                'id_labor' => (int) $r->id_labor,
                'nombre' => $r->nombre,
            ];
        }
        return $map;
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
     * Soft-delete: marca el activo como "Dado de Baja" y registra la accion
     * en cambios_log para trazabilidad.
     *
     * Si el activo ya estaba en Dado de Baja, NO agrega entrada duplicada
     * al log (mantiene la trazabilidad limpia).
     */
    public static function eliminar_activo(
        int $id_activo,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ): int {
        $original = self::get_activos(id_activo: $id_activo);

        $logPrevio = [];
        if ($original !== null) {
            $logPrevio = self::decodeCambiosLog($original->cambios_log ?? null);
        }

        if ($id_empleado !== null && $nombre_empleado !== null && $original !== null) {
            $estadoAnterior = $original->estado ?? null;
            if ($estadoAnterior !== EstadoActivoFijo::DadoDeBaja->value) {
                $logPrevio[] = [
                    'id_empleado' => $id_empleado,
                    'nombre_empleado' => $nombre_empleado,
                    'motivo' => null,
                    'update_at' => now()->toDateTimeString(),
                    'cambios' => [[
                        'campo_bd' => 'estado',
                        'campo' => 'Estado',
                        'valor_anterior' => $estadoAnterior,
                        'valor_nuevo' => EstadoActivoFijo::DadoDeBaja->value,
                    ]],
                ];
            }
        }

        $updatePayload = ['estado' => EstadoActivoFijo::DadoDeBaja->value];
        if (count($logPrevio) > 0 && $original !== null) {
            $updatePayload['cambios_log'] = json_encode($logPrevio, JSON_UNESCAPED_UNICODE);
        }

        $affected = DB::table('activo_fijo')
            ->where('id', $id_activo)
            ->update($updatePayload);

        return (int) $affected;
    }

    /**
     * Mapeo campo_bd => nombre visible para el log de cambios.
     * Mantener sincronizado con los campos que actualizar_activo() acepta en $data.
     * NOTA: 'estado' SI está aquí — la edición sí puede cambiar estado.
     * (A diferencia de Productos/Lotes/Clientes, aquí no exponemos estado en un
     * Select de Edit, pero el Controller lo acepta opcionalmente.)
     */
    private const ACTIVO_CAMBIOS_LABELS = [
        'codigo' => 'Código',
        'numero_serie' => 'Número de Serie',
        'modelo' => 'Modelo',
        'yearcito_modelo' => 'Año/Modelo',
        'descripcion' => 'Descripción',
        'serie_placa' => 'Serie Placa',
        'numero_placa' => 'Número Placa',
        'id_labor' => 'Labor',
        'estado' => 'Estado',
        'especificaciones' => 'Especificaciones',
        'id_empleado_responsable' => 'Empleado Responsable',
        'serie_factura_compra' => 'Serie Factura',
        'numero_factura_compra' => 'Número Factura',
        'costo_compra' => 'Costo de Compra',
        'costo_promedio_base' => 'Costo Promedio Base',
    ];

    /**
     * Tipo PHP esperado por cada campo. Se usa SOLO para la normalización del diff,
     * de modo que "" vs null o 0 vs "0" no genere falsos positivos.
     */
    private const ACTIVO_CAMBIOS_TIPOS = [
        'codigo' => 'string',
        'numero_serie' => 'string',
        'modelo' => 'string',
        'yearcito_modelo' => 'int',
        'descripcion' => 'string',
        'serie_placa' => 'string',
        'numero_placa' => 'string',
        'id_labor' => 'int',
        'estado' => 'string',
        'especificaciones' => 'json',
        'id_empleado_responsable' => 'int',
        'serie_factura_compra' => 'string',
        'numero_factura_compra' => 'string',
        'costo_compra' => 'float',
        'costo_promedio_base' => 'float',
    ];

    /**
     * Normaliza un valor para comparación fiable:
     *  - string: '' se trata como null
     *  - json: normalizamos al string canónico (mismo orden de claves)
     *  - num: casteamos al tipo declarado
     */
    private static function normalizarParaCompararActivo(
        mixed $valor,
        string $tipo,
    ): mixed {
        if ($tipo === 'json') {
            if ($valor === null || $valor === '') return null;
            if (is_array($valor)) {
                $rebuilt = [];
                foreach ($valor as $k => $v) {
                    $rebuilt[(string) $k] = $v;
                }
                ksort($rebuilt);
                return json_encode($rebuilt, JSON_UNESCAPED_UNICODE);
            }
            if (is_string($valor)) {
                $decoded = json_decode($valor, true);
                if (is_array($decoded)) {
                    ksort($decoded);
                    return json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }
            return null;
        }
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
     * Compara el activo previo (object) con el nuevo (object) y devuelve
     * el array de cambios_log listo para persistir. Mantiene el log previo y
     * solo agrega entrada si hay al menos un campo modificado.
     */
    public static function calcularDiffCambiosActivo(
        $original,
        $nuevo,
        int $id_empleado,
        string $nombre_empleado
    ): array {
        $logPrevio = [];
        if ($nuevo !== null) {
            $logPrevio = self::decodeCambiosLog($nuevo->cambios_log ?? null);
        }

        $cambios = [];
        foreach (self::ACTIVO_CAMBIOS_LABELS as $campoBd => $label) {
            if (!isset(self::ACTIVO_CAMBIOS_TIPOS[$campoBd])) {
                continue;
            }
            $tipo = self::ACTIVO_CAMBIOS_TIPOS[$campoBd];

            $valorAnterior = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valorNuevo = $nuevo !== null ? ($nuevo->{$campoBd} ?? null) : null;

            $anteriorNorm = self::normalizarParaCompararActivo($valorAnterior, $tipo);
            $nuevoNorm = self::normalizarParaCompararActivo($valorNuevo, $tipo);

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
     * Persiste el log calculado por calcularDiffCambiosActivo() sobre el activo.
     * Usado por ActivosService después de actualizar metadata + ubicación.
     */
    public static function appendCambiosLog(int $id_activo, array $cambiosLog): int
    {
        if (empty($cambiosLog)) {
            return 0;
        }
        return DB::table('activo_fijo')
            ->where('id', $id_activo)
            ->update(['cambios_log' => json_encode($cambiosLog, JSON_UNESCAPED_UNICODE)]);
    }
}
