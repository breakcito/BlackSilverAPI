<?php

namespace App\Shared\Enums\ActivoFijo;

enum EstadoActivoFijo: string
{
    case EnUso = 'En Uso';
    case EnMantenimiento = 'En Mantenimiento';
    case EnAlmacen = 'En Almacén';
    case DadoDeBaja = 'Dado de Baja';
}
