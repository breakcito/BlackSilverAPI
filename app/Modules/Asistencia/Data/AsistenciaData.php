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
 * NOTA: la tabla `oficina` no existe en el esquema actual; las programaciones
 * solo usan almacén o labor como lugar de trabajo. Si en el futuro se añade,
 * reintroducir el LEFT JOIN correspondiente.
 */
class AsistenciaData
{
    /**
     * Listado agrupado por empleado para una ventana de fechas (modo "Empleados").
     *
     * Devuelve una fila por empleado que tenga al menos una asistencia en el rango,
     * junto con todas sus marcaciones del período y un resumen del contrato
     * vigente en la primera fecha del rango (para mostrar sueldo/salario).
     *
     * @param  array<string, mixed>  $filtros  mes, year, id_almacen, id_labor, id_empleado, q
     * @return array<int, array<string, mixed>>
     */
    public static function get_asistencias_agrupadas(array $filtros): array
    {
        $bindings = [];
        $where = self::construir_where($filtros, $bindings);
        $filtro_lugar_contrato = self::construir_filtro_lugar_contrato($filtros, $bindings);

        // Cabecera: una fila por (empleado, fecha). Se agrupa en PHP.
        // El INNER JOIN a contrato_trabajo es OBLIGATORIO: las columnas ct.*
        // se referencian en el SELECT, y sin un contrato vigente no podemos
        // calcular la planilla. Los empleados con id_contrato_vigente = NULL
        // (huérfanos por falta de contrato Vigente) quedan excluidos.
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
            ct.tipo_contrato,
            ct.sueldo_base,
            ct.salario_diario,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ct.fecha_inicio AS contrato_fecha_inicio,
            ct.fecha_fin AS contrato_fecha_fin,
            car.nombre AS cargo_nombre,
            are.nombre AS area_nombre,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            tl.total_horas AS turno_total_horas,
            COALESCE(alm.nombre, lab.nombre) AS lugar_nombre,
            COALESCE(alm.id, lab.id) AS lugar_id,
            CASE
                WHEN alm.id IS NOT NULL THEN 'almacen'
                WHEN lab.id IS NOT NULL THEN 'labor'
                ELSE NULL
            END AS lugar_tipo,
            DATE(a.fecha_hora_ingreso) AS fecha,
            DAYNAME(DATE(a.fecha_hora_ingreso)) AS dia_semana
        FROM asistencia a
        INNER JOIN empleado emp ON emp.id = a.id_empleado
        INNER JOIN contrato_trabajo ct ON ct.id = emp.id_contrato_vigente
        LEFT JOIN cargo car ON car.id = ct.id_cargo
        LEFT JOIN area are ON are.id = car.id_area
        LEFT JOIN programacion_horario ph ON ph.id = a.id_programacion_horario
        LEFT JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        LEFT JOIN almacen alm ON alm.id = ph.id_almacen
        LEFT JOIN labor lab ON lab.id = ph.id_labor
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
            $row['salario_diario'] = $row['salario_diario'] !== null ? (float) $row['salario_diario'] : null;
            $row['asistencia_es_manual'] = (bool) $row['asistencia_es_manual'];
            $row['contrato_indefinido'] = (bool) $row['contrato_indefinido'];

            return $row;
        }, $rows);
    }

    /**
     * Devuelve la asistencia del día para un empleado, si existe.
     */
    public static function get_asistencia_del_dia(int $id_empleado, string $fecha): ?object
    {
        return DB::table('asistencia as a')
            ->where('a.id_empleado', $id_empleado)
            ->whereDate('a.fecha_hora_ingreso', $fecha)
            ->orderByDesc('a.created_at')
            ->first();
    }

    /**
     * UPSERT: actualiza la fila del día si existe, o la inserta si no.
     * La jornada_trabajada se SUMA si la fila ya existe.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function upsert_asistencia_diaria(int $id_empleado, string $fecha, array $payload): int
    {
        return DB::transaction(function () use ($id_empleado, $fecha, $payload) {
            $existente = self::get_asistencia_del_dia($id_empleado, $fecha);

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

            // jornada_trabajada se SUMA.
            $jornada_nueva = (float) ($payload['jornada_trabajada'] ?? 0);
            $jornada_existente = (float) $existente->jornada_trabajada;
            $update['jornada_trabajada'] = $jornada_existente + $jornada_nueva;

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
     * Si se filtra por lugar (almacén/labor), agrega un WHERE adicional sobre
     * la columna de lugar del contrato vigente del empleado.
     *
     * El INNER JOIN a contrato_trabajo ya está en la query principal; este
     * helper solo agrega el filtro condicional.
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

        // Solo soportamos almacén y labor. 'oficina' no existe en el esquema.
        $col = match ($tipo) {
            'almacen' => 'id_almacen',
            'labor' => 'id_labor',
            default => null,
        };

        if ($col === null) {
            return '';
        }

        $bindings['lugar_id'] = (int) $lugar;

        return "AND ct.{$col} = :lugar_id";
    }
}
