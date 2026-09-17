<?php

namespace App\Shared\Enums\ControlUso;

/**
 * Estados posibles para `control_uso_activo.estado`.
 *
 * El valor en BD es string (VARCHAR(64)) y el cast del modelo
 * `ControlUsoActivo` mapea a este enum. Usar el enum garantiza que
 * los strings sean consistentes y evita typos entre el bulk (que setea
 * 'Activo' al crear) y el endpoint de anulacion (que setea 'Anulado').
 *
 * - Activo: registro vigente, aparece en listados y Excel.
 * - Anulado: soft-delete. El backend reingresa stock, registra kardex
 *   y elimina fisicamente los consumos asociados. El registro se
 *   conserva para auditoria pero queda excluido de listados/Excel.
 */
enum EstadoControlUso: string
{
    case Activo = "Activo";
    case Anulado = "Anulado";
}
