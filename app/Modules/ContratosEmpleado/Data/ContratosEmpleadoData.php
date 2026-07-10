<?php

namespace App\Modules\ContratosEmpleado\Data;

use App\Models\ContratoTrabajo;
use App\Models\Empleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ContratosEmpleadoData
{
    /**
     * Listar contratos de trabajo con filtros opcionales.
     */
    public static function get_contratos(
        ?int $id_empleado = null,
        ?int $id_contrato = null,
        ?EstadoBase $estado = null,
    ) {
        $sql = '
        SELECT
            ct.id AS id_contrato,
            ct.id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado,
            ct.id_cargo,
            car.nombre AS cargo,
            ct.id_empresa,
            emp_asoc.razon_social AS empresa,
            ct.id_almacen,
            alm.nombre AS almacen,
            ct.id_labor,
            lab.nombre AS labor,
            lab.id_mina AS id_mina_labor,
            mina_lab.nombre AS mina_nombre,
            ct.id_oficina,
            ct.tipo_contrato,
            ct.sueldo_base,
            ct.salario_diario,
            ct.fecha_inicio,
            ct.por_tiempo_indefinido,
            ct.evidencias,
            ct.fecha_fin,
            ct.duracion,
            ct.periodo_duracion,
            DATEDIFF(ct.fecha_fin, ct.fecha_inicio) AS duracion_dias,
            ct.fecha_fin_anticipada,
            ct.created_at,
            ct.estado
        FROM contrato_trabajo ct
        INNER JOIN empleado emp ON emp.id = ct.id_empleado
        LEFT JOIN cargo car ON car.id = ct.id_cargo
        LEFT JOIN empresa emp_asoc ON emp_asoc.id = ct.id_empresa
        LEFT JOIN almacen alm ON alm.id = ct.id_almacen
        LEFT JOIN labor lab ON lab.id = ct.id_labor
        LEFT JOIN mina mina_lab ON mina_lab.id = lab.id_mina
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_contrato !== null) {
            $sql .= ' AND ct.id = :id_contrato';
            $params['id_contrato'] = $id_contrato;

            return DB::selectOne($sql, $params) ?: (object) [];
        }

        if ($id_empleado !== null) {
            $sql .= ' AND ct.id_empleado = :id_empleado';
            $params['id_empleado'] = $id_empleado;
        }

        if ($estado !== null) {
            $sql .= ' AND ct.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY ct.fecha_inicio DESC, ct.id DESC';

        $rows = DB::select($sql, $params);

        // Cast explícito: DATEDIFF devuelve un entero en MySQL, pero el driver
        // PDO lo entrega como string. El front lo espera como number.
        return array_map(function ($row) {
            $row = (array) $row;
            if (array_key_exists('duracion_dias', $row) && $row['duracion_dias'] !== null) {
                $row['duracion_dias'] = (int) $row['duracion_dias'];
            }

            return $row;
        }, $rows);
    }

    /**
     * Crear un contrato de trabajo (INSERT puro, sin validaciones).
     * Usado tanto desde el flujo directo (sin validación) como desde el Service (con validación).
     *
     * Calcula automáticamente `duracion_dias` y `fecha_fin` si no son indefinidos.
     *
     * @param  array  $payload  Campos completos del contrato
     * @param  string|null  $evidenciasJson  JSON serializado con metadata de archivos (o null)
     */
    public static function crear_contrato(array $payload, ?string $evidenciasJson): int
    {
        if ($evidenciasJson !== null) {
            $payload['evidencias'] = $evidenciasJson;
        } else {
            $payload['evidencias'] = null;
        }

        $payload['estado'] = $payload['estado'] ?? EstadoBase::Activo->value;
        $payload['created_at'] = now();

        // No se persiste `duracion_dias` en la BD (no existe la columna).
        // Se calcula en el SELECT via DATEDIFF(fecha_fin, fecha_inicio).

        return ContratoTrabajo::insertGetId($payload);
    }

    /**
     * Marcar como finalizado anticipadamente
     */
    public static function finalizar_anticipado(int $id_contrato, string $fecha_fin_anticipada): bool
    {
        return (bool) ContratoTrabajo::where('id', $id_contrato)->update([
            'fecha_fin_anticipada' => $fecha_fin_anticipada,
            'estado' => EstadoBase::Inactivo->value,
        ]);
    }

    /**
     * Actualizar el id_contrato_vigente del empleado
     */
    public static function update_id_contrato_vigente_empleado(int $id_empleado, ?int $id_contrato): bool
    {
        return (bool) Empleado::where('id', $id_empleado)->update([
            'id_contrato_vigente' => $id_contrato,
        ]);
    }

    /**
     * Verificar si ya existe un contrato Activo para el empleado con misma fecha_inicio y mismo cargo.
     */
    public static function existe_contrato_activo(
        int $id_empleado,
        int $id_cargo,
        string $fecha_inicio,
        ?int $id_contrato_excluir = null
    ): bool {
        $query = ContratoTrabajo::query()
            ->where('id_empleado', $id_empleado)
            ->where('id_cargo', $id_cargo)
            ->where('fecha_inicio', $fecha_inicio)
            ->where('estado', EstadoBase::Activo->value);

        if ($id_contrato_excluir !== null) {
            $query->where('id', '!=', $id_contrato_excluir);
        }

        return $query->exists();
    }

    /**
     * Obtener todos los contratos de un empleado (historial completo)
     */
    public static function get_historial_por_empleado(int $id_empleado): array
    {
        $sql = '
        SELECT
            ct.id AS id_contrato,
            ct.id_empleado,
            ct.id_cargo,
            car.nombre AS cargo,
            ct.id_empresa,
            emp_asoc.razon_social AS empresa,
            ct.id_almacen,
            alm.nombre AS almacen,
            ct.id_labor,
            lab.nombre AS labor,
            lab.id_mina AS id_mina_labor,
            mina_lab.nombre AS mina_nombre,
            ct.tipo_contrato,
            ct.sueldo_base,
            ct.salario_diario,
            ct.fecha_inicio,
            ct.por_tiempo_indefinido,
            ct.evidencias,
            ct.fecha_fin,
            ct.duracion,
            ct.periodo_duracion,
            DATEDIFF(ct.fecha_fin, ct.fecha_inicio) AS duracion_dias,
            ct.fecha_fin_anticipada,
            ct.created_at,
            ct.estado
        FROM contrato_trabajo ct
        LEFT JOIN cargo car ON car.id = ct.id_cargo
        LEFT JOIN empresa emp_asoc ON emp_asoc.id = ct.id_empresa
        LEFT JOIN almacen alm ON alm.id = ct.id_almacen
        LEFT JOIN labor lab ON lab.id = ct.id_labor
        LEFT JOIN mina mina_lab ON mina_lab.id = lab.id_mina
        WHERE ct.id_empleado = :id_empleado
        ORDER BY ct.fecha_inicio DESC, ct.id DESC
        ';

        $rows = DB::select($sql, ['id_empleado' => $id_empleado]);

        return array_map(function ($row) {
            $row = (array) $row;
            if (array_key_exists('duracion_dias', $row) && $row['duracion_dias'] !== null) {
                $row['duracion_dias'] = (int) $row['duracion_dias'];
            }

            return $row;
        }, $rows);
    }

    /**
     * Calcular fecha_fin a partir de fecha_inicio, duracion y periodo_duracion.
     * Devuelve string YYYY-MM-DD o null si no aplica.
     */
    public static function calcular_fecha_fin(
        string $fecha_inicio,
        int $duracion,
        string $periodo_duracion,
    ): string {
        $inicio = Carbon::parse($fecha_inicio);

        return match ($periodo_duracion) {
            'diario' => $inicio->copy()->addDays($duracion)->toDateString(),
            'semanal' => $inicio->copy()->addWeeks($duracion)->toDateString(),
            'mensual' => $inicio->copy()->addMonths($duracion)->toDateString(),
            'anual' => $inicio->copy()->addYears($duracion)->toDateString(),
            default => $inicio->toDateString(),
        };
    }

    /**
     * Listar ids de contratos Activos no indefinidos cuya fecha_fin ya pasó.
     * Usado por el comando programado `contratos:inactivar-vencidos`.
     *
     * @return array<int, int>
     */
    public static function get_ids_contratos_vencidos_no_indefinidos(?string $fecha_referencia = null): array
    {
        $fecha = $fecha_referencia ?? Carbon::now()->toDateString();

        $rows = DB::table('contrato_trabajo')
            ->where('estado', EstadoBase::Activo->value)
            ->where('por_tiempo_indefinido', 0)
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', $fecha)
            ->select('id')
            ->get();

        return $rows->map(fn ($r) => (int) $r->id)->all();
    }

    /**
     * Inactivar contratos por ids. Devuelve la cantidad afectada.
     *
     * @param  array<int, int>  $ids_contratos
     */
    public static function inactivar_contratos(array $ids_contratos): int
    {
        if (empty($ids_contratos)) {
            return 0;
        }

        return DB::table('contrato_trabajo')
            ->whereIn('id', $ids_contratos)
            ->where('estado', EstadoBase::Activo->value)
            ->update(['estado' => EstadoBase::Inactivo->value]);
    }
}
