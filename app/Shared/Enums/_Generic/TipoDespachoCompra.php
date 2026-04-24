<?php

namespace App\Shared\Enums\_Generic;

enum TipoDespachoCompra: string
{
    case Recojo = 'Recojo'; // los productos sera recogidos por nosotros
    case Envio = 'Envío'; // los productos seran enviados por el proveedor al almacen
}
