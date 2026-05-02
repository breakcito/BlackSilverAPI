<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOrdenCompraDetalleLog: string
{
    case Pendiente = 'Pendiente';
    case EnRecepcion = 'En Recepción';
    case NuevaRecepcion = 'Nueva Recepción';
    case RecepcionCompleta = 'Recepción Completa';

    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::Pendiente => 'Compra formalizada. El producto se encuentra a la espera del despacho por parte del proveedor',
            self::EnRecepcion => 'Recepción en curso. Se han registrado ingresos de este producto en el almacén de destino',
            self::NuevaRecepcion => "Se registró una nueva recepción de {$dinamico} unidad(es).",
            self::RecepcionCompleta => "La recepción de este producto ha sido completada.",
        };
    }
}
