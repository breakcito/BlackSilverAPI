<?php

namespace App\Shared\Enums\OrdenCompra;

enum EstadoOCComprobante: string
{
    case Generado = "Generado";
    case EnProcesoPago = "En Proceso de Pago";
    case Pagado = "Pagado";
}
