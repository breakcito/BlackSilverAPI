<?php

namespace App\Shared\Enums\VentaCarbon;

/**
 * Estados de una liquidacion de venta de carbon.
 *
 * - Registrada: se creo la liquidacion con sus cargas y precios. Todavia se
 *   puede editar.
 * - Confirmada: el cliente aprobo la liquidacion. A partir de aqui se puede
 *   registrar el comprobante.
 *
 * Es DERIVADO de `liquidacion_venta_carbon.esta_confirmado`: la tabla guarda
 * el booleano y la API expone la etiqueta.
 */
enum EstadoLiquidacionVentaCarbon: string
{
    case Registrada = 'Registrada';
    case Confirmada = 'Confirmada';

    /**
     * Traduce el booleano de la tabla al estado del proceso.
     */
    public static function desdeConfirmado(bool $estaConfirmado): self
    {
        return $estaConfirmado ? self::Confirmada : self::Registrada;
    }
}
