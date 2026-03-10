<?php

namespace App\Shared\Enums\RequerimientoAlmacen;

enum EstadoRequerimiento: string
{
    case Generado = 'Generado';
    case EnProceso = 'En Proceso'; // cuando se aprobo al menos un item o se consulto con logistica
    case Cerrado = 'Cerrado';
    case Anulado = 'Anulado';
}
