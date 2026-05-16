<?php

namespace App\Shared\Enums\ActivoFijo;

enum MovimientoActivoFijo: string
{
    case DeAlmacenAMina = 'De Almacén a Mina'; // indica si es que el activo paso de estar en un almacen a una mina
    case DeAlmacenAAlmacen = 'De Almacén a Almacén'; // indica si es que el activo paso de estar en un almacen a otro almacen
    // 
    case DeMinaAMina = 'De Mina a Mina'; // indica si es que el activo paso de estar en una mina a otra mina
    case DeMinaAAlmacen = 'De Mina a Almacén'; // indica si es que el activo paso de estar en una mina a un almacen
    //
    case NuevoActivo = 'Nuevo Activo'; // indica si es que el activo es nuevo (no se ha movido a otro lugar)
    case DadoDeBaja = 'Dado de Baja'; // indica si es que el activo fue dado de baja
}
