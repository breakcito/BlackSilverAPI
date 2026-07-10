<?php

namespace App\Modules\ProgramacionHorarios\Data;

use App\Models\ProgramacionHorario;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProgramacionHorarioData
{
    /**
     * Listar programaciones de horario con filtros opcionales.
     *
     * @return array<int, object>|object
     */
    public static function get_programaciones(
        ?int $id_programacion = null,
        ?int $id_empleado = null,
        ?int $id_turno_laboral = null,
        ?EstadoBase $estado = null,
        ?string $fecha_desde = null,
        ?string $fecha_hasta = null,
        ?int $id_almacen = null,
        ?int $id_labor = null,
        ?int $id_oficina = null,
    ) {
        $sql = '
        SELECT
            ph.id,
            ph.id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado,
            emp.url_foto AS empleado_url_foto,
            ph.id_contrato_trabajo,
            ct.tipo_contrato,
            ct.fecha_inicio AS contrato_fecha_inicio,
            ct.fecha_fin AS contrato_fecha_fin,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ph.id_turno_laboral,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            ph.id_oficina,
            ph.id_almacen,
            ph.id_labor,
            alm.nombre AS almacen_nombre,
            lab.nombre AS labor_nombre,
            NULL AS oficina_nombre,
            ph.fecha_inicio,
            ph.por_tiempo_indefinido,
            ph.fecha_fin,
            ph.dias_laborables,
            ph.estado
        FROM programacion_horario ph
        INNER JOIN empleado emp ON emp.id = ph.id_empleado
        INNER JOIN contrato_trabajo ct ON ct.id = ph.id_contrato_trabajo
        INNER JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        LEFT JOIN almacen alm ON alm.id = ph.id_almacen
        LEFT JOIN labor lab ON lab.id = ph.id_labor
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_programacion !== null) {
            $sql .= ' AND ph.id = :id_programacion';
            $params['id_programacion'] = $id_programacion;

            return DB::selectOne($sql, $params) ?: (object) [];
        }

        if ($id_empleado !== null) {
            $sql .= ' AND ph.id_empleado = :id_empleado';
            $params['id_empleado'] = $id_empleado;
        }

        if ($id_turno_laboral !== null) {
            $sql .= ' AND ph.id_turno_laboral = :id_turno_laboral';
            $params['id_turno_laboral'] = $id_turno_laboral;
        }

        if ($estado !== null) {
            $sql .= ' AND ph.estado = :estado';
            $params['estado'] = $estado->value;
        }

        // Filtro por rango de fechas de la programación (solapamiento con [fecha_desde, fecha_hasta]).
        // Se consideran activas las programaciones por tiempo indefinido dentro del rango.
        if ($fecha_desde !== null) {
            $sql .= ' AND (ph.por_tiempo_indefinido = 1 OR ph.fecha_fin IS NULL OR ph.fecha_fin >= :fecha_desde)';
            $params['fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta !== null) {
            $sql .= ' AND ph.fecha_inicio <= :fecha_hasta';
            $params['fecha_hasta'] = $fecha_hasta;
        }

        if ($id_almacen !== null) {
            $sql .= ' AND ph.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        if ($id_labor !== null) {
            $sql .= ' AND ph.id_labor = :id_labor';
            $params['id_labor'] = $id_labor;
        }

        if ($id_oficina !== null) {
            $sql .= ' AND ph.id_oficina = :id_oficina';
            $params['id_oficina'] = $id_oficina;
        }

        // Orden: por fecha_inicio ASC, hora_ingreso del turno ASC (mañana → noche), id ASC.
        $sql .= ' ORDER BY ph.fecha_inicio ASC, tl.hora_ingreso ASC, ph.id ASC';

        $rows = DB::select($sql, $params);

        return array_map(function ($row) {
            $row = (array) $row;
            $row['por_tiempo_indefinido'] = (bool) ($row['por_tiempo_indefinido'] ?? 0);
            $row['contrato_indefinido'] = (bool) ($row['contrato_indefinido'] ?? 0);

            return $row;
        }, $rows);
    }

    /**
     * Obtener programaciones que se solapan con la semana indicada.
     * La grilla semanal del frontend consume este método.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_grilla_semanal(string $fecha_inicio_semana, string $fecha_fin_semana): array
    {
        $rows = self::get_programaciones(
            fecha_desde: $fecha_inicio_semana,
            fecha_hasta: $fecha_fin_semana,
        );

        return array_values($rows);
    }

    /**
     * Insertar una programacion_horario (uso directo desde Service).
     *
     * @param  array  $payload  ['id_empleado', 'id_contrato_trabajo', 'id_turno_laboral', 'fecha_inicio', 'por_tiempo_indefinido', 'fecha_fin', 'dias_laborables', 'estado']
     */
    public static function crear_programacion(array $payload): int
    {
        $payload['estado'] = $payload['estado'] ?? EstadoBase::Activo->value;

        return ProgramacionHorario::insertGetId($payload);
    }

    /**
     * Insert masivo de programaciones para N empleados (mismo turno, mismo rango).
     *
     * @param  array  $registros  Cada elemento con la misma forma que $payload de crear_programacion
     */
    public static function crear_programaciones_masivo(array $registros): array
    {
        if (empty($registros)) {
            return [];
        }

        $ids = [];
        foreach ($registros as $payload) {
            $ids[] = self::crear_programacion($payload);
        }

        return $ids;
    }

    /**
     * Actualizar una programacion_horario.
     */
    public static function actualizar_programacion(int $id_programacion, array $payload): bool
    {
        return (bool) ProgramacionHorario::where('id', $id_programacion)->update($payload);
    }

    /**
     * Cambiar el estado (Activo/Inactivo) de una programacion_horario.
     */
    public static function cambiar_estado(int $id_programacion, string $estado): bool
    {
        return (bool) ProgramacionHorario::where('id', $id_programacion)->update(['estado' => $estado]);
    }

    /**
     * Verificar si ya existe una programación Activa para el mismo empleado + contrato + turno + fecha_inicio.
     * Sirve para evitar duplicados al asignar.
     */
    public static function existe_programacion_activa(
        int $id_empleado,
        int $id_contrato_trabajo,
        int $id_turno_laboral,
        string $fecha_inicio,
        ?int $id_programacion_excluir = null,
    ): bool {
        $query = ProgramacionHorario::query()
            ->where('id_empleado', $id_empleado)
            ->where('id_contrato_trabajo', $id_contrato_trabajo)
            ->where('id_turno_laboral', $id_turno_laboral)
            ->where('fecha_inicio', $fecha_inicio)
            ->where('estado', EstadoBase::Activo->value);

        if ($id_programacion_excluir !== null) {
            $query->where('id', '!=', $id_programacion_excluir);
        }

        return $query->exists();
    }

    /**
     * Listar los empleados con contrato vigente Activo. Usado por el Service
     * para validar la elegibilidad antes de asignar.
     *
     * Devuelve además `fecha_fin_contrato` y `contrato_indefinido` para que el Service
     * pueda evaluar si el contrato cubre la programación solicitada.
     *
     * @param  array<int, int>  $ids_empleados
     * @return array<int, array<string, mixed>>
     */
    public static function get_empleados_con_contrato_vigente(array $ids_empleados): array
    {
        if (empty($ids_empleados)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_empleados), '?'));

        $sql = "
        SELECT
            emp.id AS id_empleado,
            emp.nombre,
            emp.apellido,
            emp.con_contrato,
            emp.id_contrato_vigente,
            ct.estado AS contrato_estado,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ct.fecha_fin AS contrato_fecha_fin
        FROM empleado emp
        INNER JOIN contrato_trabajo ct ON ct.id = emp.id_contrato_vigente
        WHERE emp.es_contratista = 0
          AND emp.estado = ?
          AND emp.con_contrato = 1
          AND emp.id_contrato_vigente IS NOT NULL
          AND ct.estado = ?
          AND emp.id IN ({$placeholders})
        ";

        $bindings = array_merge(
            [EstadoBase::Activo->value, EstadoBase::Activo->value],
            $ids_empleados
        );

        return array_map(function ($row) {
            $row = (array) $row;
            $row['con_contrato'] = (bool) ($row['con_contrato'] ?? 0);
            $row['contrato_indefinido'] = (bool) ($row['contrato_indefinido'] ?? 0);

            return $row;
        }, DB::select($sql, $bindings));
    }

    /**
     * Verifica si la nueva programación se cruza con alguna existente del mismo empleado.
     *
     * Regla estricta: para que haya cruce, deben cumplirse las 3 condiciones:
     *  1. Solapamiento de rango de fechas (considerando indefinidos).
     *  2. Intersección de días laborables (bitwise AND de los strings "0101010" no da "0000000").
     *  3. Cruce de rangos de hora del turno (nueva.ingreso < existente.salida Y existente.ingreso < nueva.salida).
     *
     * Devuelve la primera programación conflictiva encontrada, o null.
     */
    public static function existe_cruce_horario(
        int $id_empleado,
        int $id_turno_laboral,
        string $dias_laborables_nuevo,
        string $fecha_inicio_nuevo,
        ?string $fecha_fin_nuevo,
        ?int $id_programacion_excluir = null,
    ): ?object {
        $nueva_indefinida = $fecha_fin_nuevo === null ? 1 : 0;

        $bindings = [
            'id_empleado' => $id_empleado,
            'estado' => EstadoBase::Activo->value,
        ];

        if ($nueva_indefinida === 1) {
            $sql_cond = "
                (ph.por_tiempo_indefinido = 1)
                OR (ph.por_tiempo_indefinido = 0 AND ph.fecha_fin >= :nueva_fecha_inicio)
            ";
            $bindings['nueva_fecha_inicio'] = $fecha_inicio_nuevo;
        } else {
            $sql_cond = "
                (ph.por_tiempo_indefinido = 1 AND ph.fecha_inicio <= :nueva_fecha_fin_indef)
                OR (ph.por_tiempo_indefinido = 0 
                    AND ph.fecha_inicio <= :nueva_fecha_fin_finit 
                    AND :nueva_fecha_inicio <= ph.fecha_fin)
            ";
            $bindings['nueva_fecha_fin_indef'] = $fecha_fin_nuevo;
            $bindings['nueva_fecha_fin_finit'] = $fecha_fin_nuevo;
            $bindings['nueva_fecha_inicio'] = $fecha_inicio_nuevo;
        }

        // 1. Buscar programaciones Activas del empleado que se solapen en rango de fechas.
        $sql = "
        SELECT
            ph.id,
            ph.id_empleado,
            ph.id_contrato_trabajo,
            ph.id_turno_laboral,
            tl.hora_ingreso,
            tl.hora_salida,
            ph.fecha_inicio,
            ph.por_tiempo_indefinido,
            ph.fecha_fin,
            ph.dias_laborables
        FROM programacion_horario ph
        INNER JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
        WHERE ph.id_empleado = :id_empleado
          AND ph.estado = :estado
          AND ({$sql_cond})
        ";

        if ($id_programacion_excluir !== null) {
            $sql .= ' AND ph.id != :id_excluir';
            $bindings['id_excluir'] = $id_programacion_excluir;
        }

        $existentes = DB::select($sql, $bindings);

        // 2 y 3. Verificar intersección de días laborables y cruce de hora.
        foreach ($existentes as $prog) {
            $diasComun = self::diasLaborablesInterseccion(
                $dias_laborables_nuevo,
                (string) $prog->dias_laborables,
            );
            if ($diasComun === '0000000') {
                continue;
            }

            // 3. Cruce de rangos de hora (por día, no por fecha, soportando cruce de medianoche).
            $hNuevaIngreso = self::parseHoraMinutos(self::getHora($id_turno_laboral, 'hora_ingreso'));
            $hNuevaSalida = self::parseHoraMinutos(self::getHora($id_turno_laboral, 'hora_salida'));
            $hExistIngreso = self::parseHoraMinutos($prog->hora_ingreso);
            $hExistSalida = self::parseHoraMinutos($prog->hora_salida);

            if (self::horasSeSolapan($hNuevaIngreso, $hNuevaSalida, $hExistIngreso, $hExistSalida)) {
                return $prog;
            }
        }

        return null;
    }

    /**
     * Calcula la intersección bitwise AND de dos strings de 7 caracteres "0"/"1".
     */
    private static function diasLaborablesInterseccion(string $a, string $b): string
    {
        $a = str_pad($a, 7, '0');
        $b = str_pad($b, 7, '0');
        $result = '';
        for ($i = 0; $i < 7; $i++) {
            $result .= ($a[$i] === '1' && $b[$i] === '1') ? '1' : '0';
        }

        return $result;
    }

    /**
     * Devuelve la hora de ingreso o salida de un turno_laboral.
     */
    private static function getHora(int $id_turno_laboral, string $campo): string
    {
        $row = DB::table('turno_laboral')->where('id', $id_turno_laboral)->first([$campo]);
        if ($row === null) {
            return '00:00:00';
        }

        return (string) $row->{$campo};
    }

    /**
     * Convierte "HH:mm:ss" o "HH:mm" a minutos desde medianoche.
     */
    private static function parseHoraMinutos(string $hora): int
    {
        $partes = explode(':', $hora);
        $h = (int) ($partes[0] ?? 0);
        $m = (int) ($partes[1] ?? 0);

        return $h * 60 + $m;
    }

    /**
     * Comprueba si dos rangos de horas se solapan en un ciclo de 24 horas.
     * Soporta turnos que cruzan la medianoche (ingreso > salida).
     */
    private static function horasSeSolapan(int $s1, int $e1, int $s2, int $e2): bool
    {
        $intervals1 = ($s1 < $e1) ? [[$s1, $e1]] : [[$s1, 1440], [0, $e1]];
        $intervals2 = ($s2 < $e2) ? [[$s2, $e2]] : [[$s2, 1440], [0, $e2]];

        foreach ($intervals1 as $int1) {
            foreach ($intervals2 as $int2) {
                if ($int1[0] < $int2[1] && $int2[0] < $int1[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
