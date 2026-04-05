<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoReposicion: string
{
    case SinReposicion = 'Sin Reposicion'; // solo para el prestamo
    case EnDespacho = 'En Despacho'; // solo para la reposicion
    case ReposicionParcial = 'Reposicion Parcial';
    case ReposicionTotal = 'Reposicion Total';
}
