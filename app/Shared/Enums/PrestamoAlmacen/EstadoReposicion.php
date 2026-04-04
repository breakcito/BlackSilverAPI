<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoReposicion: string
{
    case SinReposicion = 'Sin Reposicion';
    case ReposicionParcial = 'Reposicion Parcial';
    case ReposicionTotal = 'Reposicion Total';
}
