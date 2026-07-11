<?php

namespace App\Shared\Enums\Contrato;

/**
 * Estados del ciclo de vida de un contrato de trabajo.
 *
 * - Vigente: contrato actual del empleado (dentro de su periodo activo).
 * - Pendiente: registrado con fecha_inicio futura; aún no inicia.
 * - TerminoAnticipado: el contrato fue reemplazado antes de su fecha_fin natural.
 *   Conserva `fecha_fin_anticipada` con la fecha en que se cerró.
 * - Finalizado: culminó su periodo estipulado (fecha_fin ya pasó).
 */
enum EstadoContrato: string
{
    case Vigente = 'Vigente';
    case Pendiente = 'Pendiente';
    case TerminoAnticipado = 'Término Anticipado';
    case Finalizado = 'Finalizado';
}
