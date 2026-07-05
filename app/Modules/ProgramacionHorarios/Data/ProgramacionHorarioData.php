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
            ph.fecha_inicio,
            ph.por_tiempo_indefinido,
            ph.fecha_fin,
            ph.dias_laborables,
            ph.estado
        FROM programacion_horario ph
        INNER JOIN empleado emp ON emp.id = ph.id_empleado
        INNER JOIN contrato_trabajo ct ON ct.id = ph.id_contrato_trabajo
        INNER JOIN turno_laboral tl ON tl.id = ph.id_turno_laboral
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

        $sql .= ' ORDER BY ph.fecha_inicio DESC, ph.id DESC';

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
}
