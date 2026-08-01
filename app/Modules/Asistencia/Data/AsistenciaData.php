<?php

namespace App\Modules\Asistencia\Data;

use Illuminate\Support\Facades\DB;

/**
 * Capa de acceso a datos del módulo Asistencia.
 *
 * La tabla `asistencia` tiene una fila por empleado por día; cuando un
 * empleado tiene múltiples turnos en el mismo día, la columna
 * `jornada_trabajada` se va SUMANDO vía UPSERT (ver `upsert_asistencia_diaria`).
 *
 * Los campos de SUELDO y TIPO_CONTRATO usados para cálculo de pago se leen
 * desde los SNAPSHOTS almacenados en `programacion_horario` (tipo_contrato,
 * sueldo_base, sueldo_diario), NO del contrato vigente. Esto permite que
 * un cambio de sueldo a mitad de mes (vía adenda) se refleje correctamente:
 * cada tramo histórico conserva su propio snapshot.
 *
 * El `contrato_trabajo` se mantiene solo para datos referenciales
 * (cargo, área, fechas de vigencia del contrato, id_contrato_vigente).
 */
class AsistenciaData
{
    /**
     * Listado de asistencias para una ventana de fechas (modo "Empleados").
     *
     * Devuelve 1 fila por (empleado, fecha, id_programacion_horario). Cada
     * asistencia es de un solo turno; si el empleado tuvo 2 turnos el mismo
     * día, se devuelven 2 filas con sus marcajes y snapshots respectivos.
     *
     * @param  array<string, mixed>  $filtros  mes, year, id_almacen, id_labor, id_oficina, id_empleado, q
     * @return array<int, array<string, mixed>>
     */
    public static function get_asistencias_agrupadas(array $filtros): array
    {
        $bindings = [];
        $where = self::construir_where($filtros, $bindings);
        $filtro_lugar_contrato = self::construir_filtro_lugar_contrato($filtros, $bindings);

        // 1 fila por (empleado, fecha, id_programacion_horario). El INNER JOIN
        // a contrato_trabajo es OBLIGATORIO: las columnas ct.* se referencian
        // en el SELECT (cargo, area, fechas). Los empleados sin contrato
        // vigente al día de la asistencia quedan excluidos.
        //
        // Los snapshots de SUELDO y TIPO_CONTRATO se leen de `programacion_horario`
        // (ph), NO de `contrato_trabajo` (ct). Si la programación no tiene
        // snapshot (caso legacy), hacemos COALESCE al contrato vigente como
        // fallback para no romper cálculos históricos.
        $sql = "
        SELECT
            a.id AS id_asistencia,
            a.id_empleado,
            a.id_programacion_horario,
            a.fecha_hora_ingreso,
            a.fecha_hora_salida,
            a.total_horas,
            a.jornada_trabajada,
            a.minutos_tardanza,
            a.es_manual AS asistencia_es_manual,
            a.created_at AS asistencia_created_at,
            emp.nombre,
            emp.apellido,
            emp.dni,
            emp.url_foto,
            emp.es_contratista,
            emp.id_contrato_vigente,
            -- Snapshots desde programacion_horario (con fallback al contrato).
            ph.tipo_contrato AS programacion_tipo_contrato,
            ph.sueldo_base AS programacion_sueldo_base,
            ph.sueldo_real AS programacion_sueldo_real,
            ph.sueldo_diario AS programacion_sueldo_diario,
            COALESCE(ph.tipo_contrato, ct.tipo_contrato) AS tipo_contrato,
            COALESCE(ph.sueldo_base, ct.sueldo_base) AS sueldo_base,
            COALESCE(ph.sueldo_real, ct.sueldo_real) AS sueldo_real,
            COALESCE(ph.sueldo_diario, ct.salario_diario) AS salario_diario,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ct.fecha_inicio AS contrato_fecha_inicio,
            ct.fecha_fin AS contrato_fecha_fin,
            car.nombre AS cargo_nombre,
            are.nombre AS area_nombre,
            mn.nombre AS mina_nombre,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            tl.total_horas AS turno_total_horas,
            COALESCE(alm.nombre, lab.nombre, ofi.nombre) AS lugar_nombre,
            COALESCE(alm.id, lab.id, ofi.id) AS lugar_id,
            CASE
                WHEN alm.id IS NOT NULL THEN 'almacen'
                WHEN lab.id IS NOT NULL THEN 'labor'
                WHEN ofi.id IS NOT NULL THEN 'oficina'
                ELSE NULL
            END AS lugar_tipo,
            DATE(a.fecha_hora_ingreso) AS fecha,
            DAYNAME(DATE(a.fecha_hora_ingreso)) AS dia_semana
        FROM asistencia a
        INNER JOIN empleado emp ON emp.id = a.id_empleado
        INNER JOIN contrato_trabajo ct ON ct.id_empleado = a.id_empleado
            AND DATE(a.fecha_hora_ingreso) >= ct.fecha_inicio
            AND (
                (ct.fecha_fin IS NULL AND ct.fecha_fin_anticipada IS NULL)
                OR DATE(a.fecha_hora_ingreso) <= COALESCE(ct.fecha_fin_anticipada, ct.fecha_fin)
            )
        LEFT JOIN cargo car ON car.id = ct.id_cargo
        LEFT JOIN area are ON are.id = car.id_area
        LEFT JOIN mina mn ON mn.id = emp.id_mina
        LEFT JOIN programacion_horario ph ON ph.id = a.id_programacion_horario
        LEFT JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        LEFT JOIN almacen alm ON alm.id = ph.id_almacen
        LEFT JOIN labor lab ON lab.id = ph.id_labor
        LEFT JOIN oficina ofi ON ofi.id = ph.id_oficina
        WHERE {$where} {$filtro_lugar_contrato}
        ORDER BY emp.nombre ASC, emp.apellido ASC, a.fecha_hora_ingreso ASC
        ";

        $rows = DB::select($sql, $bindings);

        // Agrupamos en PHP por (id_empleado, id_asistencia). La cabecera queda
        // explícita para que el frontend pueda mostrar cada marcación individual.
        return array_map(function ($row) {
            $row = (array) $row;
            $row['total_horas'] = $row['total_horas'] !== null ? (float) $row['total_horas'] : null;
            $row['jornada_trabajada'] = $row['jornada_trabajada'] !== null ? (float) $row['jornada_trabajada'] : null;
            $row['turno_total_horas'] = $row['turno_total_horas'] !== null ? (float) $row['turno_total_horas'] : null;
            $row['sueldo_base'] = $row['sueldo_base'] !== null ? (float) $row['sueldo_base'] : null;
            $row['sueldo_real'] = $row['sueldo_real'] !== null ? (float) $row['sueldo_real'] : null;
            $row['salario_diario'] = $row['salario_diario'] !== null ? (float) $row['salario_diario'] : null;
            $row['programacion_sueldo_base'] = $row['programacion_sueldo_base'] !== null ? (float) $row['programacion_sueldo_base'] : null;
            $row['programacion_sueldo_real'] = $row['programacion_sueldo_real'] !== null ? (float) $row['programacion_sueldo_real'] : null;
            $row['programacion_sueldo_diario'] = $row['programacion_sueldo_diario'] !== null ? (float) $row['programacion_sueldo_diario'] : null;
            $row['asistencia_es_manual'] = (bool) $row['asistencia_es_manual'];
            $row['contrato_indefinido'] = (bool) $row['contrato_indefinido'];

            return $row;
        }, $rows);
    }

    /**
     * Devuelve la asistencia del día y turno (id_programacion_horario) para un
     * empleado, si existe. Si no se pasa id_programacion_horario, devuelve
     * cualquier asistencia del día (compatibilidad hacia atrás).
     */
    public static function get_asistencia_del_dia(int $id_empleado, string $fecha, ?int $id_programacion_horario = null): ?object
    {
        $query = DB::table('asistencia as a')
            ->where('a.id_empleado', $id_empleado)
            ->whereDate('a.fecha_hora_ingreso', $fecha);

        if ($id_programacion_horario !== null && $id_programacion_horario > 0) {
            $query->where('a.id_programacion_horario', $id_programacion_horario);
        }

        return $query->orderByDesc('a.created_at')->first();
    }

    /**
     * UPSERT: actualiza la fila del (día, turno) si existe, o la inserta si no.
     * Una asistencia es 1 fila por (empleado, día, id_programacion_horario).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_asistencia_diaria(int $id_empleado, string $fecha, array $payload, bool $sobreescribir_jornada = false): int
    {
        $id_programacion_horario = isset($payload['id_programacion_horario'])
            ? (int) $payload['id_programacion_horario']
            : 0;

        return DB::transaction(function () use ($id_empleado, $fecha, $payload, $id_programacion_horario) {
            $existente = self::get_asistencia_del_dia($id_empleado, $fecha, $id_programacion_horario);

            if ($existente === null) {
                $payload['id_empleado'] = $id_empleado;
                $payload['created_at'] = now();

                return (int) DB::table('asistencia')->insertGetId($payload);
            }

            $update = [];
            // Solo sobreescribe si la clave viene explícita en $payload.
            foreach (['fecha_hora_ingreso', 'minutos_tardanza', 'fecha_hora_salida', 'total_horas', 'es_manual', 'id_programacion_horario'] as $campo) {
                if (array_key_exists($campo, $payload)) {
                    $update[$campo] = $payload[$campo];
                }
            }

            if (array_key_exists('jornada_trabajada', $payload)) {
                $update['jornada_trabajada'] = (float) $payload['jornada_trabajada'];
            }

            DB::table('asistencia')
                ->where('id', $existente->id)
                ->update($update);

            return (int) $existente->id;
        });
    }

    /**
     * Construye el WHERE común para los listados del módulo.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $bindings  Se llena por referencia con los valores para prepared statements.
     */
    private static function construir_where(array $filtros, array &$bindings): string
    {
        $partes = ['1 = 1'];

        // Rango de fechas: mes + year (YYYY-MM-01 a YYYY-MM-ultimo_dia).
        if (! empty($filtros['mes']) && ! empty($filtros['year'])) {
            $mes = (int) $filtros['mes'];
            $year = (int) $filtros['year'];
            $ultimo_dia = (int) date('t', mktime(0, 0, 0, $mes, 1, $year));
            $inicio = sprintf('%04d-%02d-01', $year, $mes);
            $fin = sprintf('%04d-%02d-%02d', $year, $mes, $ultimo_dia);
            $partes[] = 'DATE(a.fecha_hora_ingreso) BETWEEN :fecha_inicio AND :fecha_fin';
            $bindings['fecha_inicio'] = $inicio;
            $bindings['fecha_fin'] = $fin;
        } elseif (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'DATE(a.fecha_hora_ingreso) BETWEEN :fecha_inicio AND :fecha_fin';
            $bindings['fecha_inicio'] = $filtros['fecha_desde'];
            $bindings['fecha_fin'] = $filtros['fecha_hasta'];
        }

        if (! empty($filtros['id_empleado'])) {
            $partes[] = 'a.id_empleado = :id_empleado';
            $bindings['id_empleado'] = (int) $filtros['id_empleado'];
        }

        // Búsqueda libre por nombre/DNI.
        if (! empty($filtros['q'])) {
            $partes[] = '(emp.nombre LIKE :q OR emp.apellido LIKE :q OR emp.dni LIKE :q)';
            $bindings['q'] = '%'.$filtros['q'].'%';
        }

        return implode(' AND ', $partes);
    }

    /**
     * Si se filtra por lugar (almacén/labor/oficina), agrega un WHERE adicional
     * sobre la columna de lugar de la programación (`programacion_horario.ph`).
     *
     * Filtramos por el lugar REAL del trabajo (la programación), no por el del
     * contrato, porque una programación puede estar en un lugar distinto del
     * contrato (cambio temporal autorizado).
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $bindings
     */
    private static function construir_filtro_lugar_contrato(array $filtros, array &$bindings): string
    {
        $lugar = $filtros['id_lugar'] ?? null;
        $tipo = $filtros['tipo_lugar'] ?? null;

        if ($lugar === null || $tipo === null) {
            return '';
        }

        // Filtramos por el lugar de la programación (ph), que es donde realmente
        // se ejecuta el trabajo. 'oficina' ahora está habilitada.
        $col = match ($tipo) {
            'almacen' => 'id_almacen',
            'labor' => 'id_labor',
            'oficina' => 'id_oficina',
            default => null,
        };

        if ($col === null) {
            return '';
        }

        $bindings['lugar_id'] = (int) $lugar;

        return "AND ph.{$col} = :lugar_id";
    }
}
