<?php

namespace App\Modules\Asistencia\Data;

use App\Models\Marcaje;

/**
 * Capa de acceso a datos del módulo Marcaje.
 *
 * Los marcajes son el log/evidencia de cada intento de marcaje (con o sin
 * asistencia asociada). Sirven para auditoría y para reconstruir el flujo
 * de un empleado durante el día.
 */
class MarcajeData
{
    /**
     * Crea un nuevo marcaje en estado inicial (post-resolución de QR).
     *
     * @param  array<string, mixed>  $payload  Columnas a insertar (sin `id` ni `created_at`).
     */
    public static function crear_marcaje(array $payload): int
    {
        $defaults = [
            'id_asistencia' => null,
            'id_empleado' => null,
            'id_programacion_horario' => null,
            'id_empleado_registro' => null,
            'tipo_marcaje' => null,
            'fecha_hora' => null,
            'evidencias' => null,
            'es_manual' => false,
            'qr_leido' => false,
            'proceso_confirmado' => false,
            'created_at' => now(),
        ];

        $data = array_merge($defaults, $payload);

        return Marcaje::insertGetId($data);
    }

    /**
     * Actualiza un marcaje existente. Solo modifica las claves provistas.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function actualizar_marcaje(int $id_marcaje, array $payload): int
    {
        if (empty($payload)) {
            return 0;
        }

        return Marcaje::where('id', $id_marcaje)->update($payload);
    }

    /**
     * Devuelve el último marcaje del día para un empleado.
     * Se interpreta como el "estado actual" de la marcación (Ingreso o Salida).
     * Si nunca marcó hoy, devuelve null.
     */
    public static function get_ultimo_marcaje_hoy(int $id_empleado): ?object
    {
        return Marcaje::query()
            ->where('id_empleado', $id_empleado)
            ->whereDate('fecha_hora', now()->toDateString())
            ->orderByDesc('fecha_hora')
            ->first();
    }

    /**
     * Listado crudo de marcajes de una asistencia específica (usado por el admin).
     *
     * @return array<int, object>
     */
    public static function get_marcajes_por_asistencia(int $id_asistencia): array
    {
        return Marcaje::query()
            ->where('id_asistencia', $id_asistencia)
            ->orderBy('fecha_hora')
            ->get()
            ->toArray();
    }

    /**
     * Listado crudo de marcajes de un empleado en un día (para vista admin / debug).
     *
     * @return array<int, object>
     */
    public static function get_marcajes_del_dia(int $id_empleado, string $fecha): array
    {
        return Marcaje::query()
            ->where('id_empleado', $id_empleado)
            ->whereDate('fecha_hora', $fecha)
            ->orderBy('fecha_hora')
            ->get()
            ->toArray();
    }
}
