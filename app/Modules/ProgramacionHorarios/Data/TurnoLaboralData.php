<?php

namespace App\Modules\ProgramacionHorarios\Data;

use App\Models\TurnoLaboral;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\ProgramacionHorario\TipoTurno;
use Illuminate\Support\Facades\DB;

class TurnoLaboralData
{
    /**
     * Listar turnos laborales con filtros opcionales.
     */
    public static function get_turnos(
        ?int $id_turno = null,
        ?EstadoBase $estado = null,
        ?TipoTurno $tipo_turno = null,
    ) {
        $sql = '
        SELECT
            tl.id,
            tl.tipo_turno,
            tl.hora_ingreso,
            tl.hora_salida,
            tl.minutos_tolerancia,
            tl.estado
        FROM turno_laboral tl
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_turno !== null) {
            $sql .= ' AND tl.id = :id_turno';
            $params['id_turno'] = $id_turno;

            return DB::selectOne($sql, $params) ?: (object) [];
        }

        if ($estado !== null) {
            $sql .= ' AND tl.estado = :estado';
            $params['estado'] = $estado->value;
        }

        if ($tipo_turno !== null) {
            $sql .= ' AND tl.tipo_turno = :tipo_turno';
            $params['tipo_turno'] = $tipo_turno->value;
        }

        $sql .= ' ORDER BY tl.tipo_turno ASC, tl.hora_ingreso ASC, tl.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Crear un nuevo turno laboral.
     *
     * @param  array  $payload  ['tipo_turno', 'hora_ingreso', 'hora_salida', 'minutos_tolerancia', 'estado']
     */
    public static function crear_turno(array $payload): int
    {
        $payload['estado'] = $payload['estado'] ?? EstadoBase::Activo->value;

        return TurnoLaboral::insertGetId($payload);
    }

    /**
     * Actualizar un turno laboral.
     *
     * @param  array  $payload  Campos a modificar (subset de columnas válidas)
     */
    public static function actualizar_turno(int $id_turno, array $payload): bool
    {
        return (bool) TurnoLaboral::where('id', $id_turno)->update($payload);
    }

    /**
     * Cambiar el estado (Activo/Inactivo) de un turno laboral.
     */
    public static function cambiar_estado(int $id_turno, string $estado): bool
    {
        return (bool) TurnoLaboral::where('id', $id_turno)->update(['estado' => $estado]);
    }
}
