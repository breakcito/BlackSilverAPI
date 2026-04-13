<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoReposicion: string
{
    case SinReposicion = 'Sin Reposicion'; // solo para el prestamo
    case EnDespacho = 'En Despacho'; // solo para la reposicion
    case ReposicionadoParcialmente = 'Reposicionado Parcialmente';
    case ReposicionCompleta = 'Reposicion Completa';
}
