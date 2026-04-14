<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoReposicionPrestamo: string
{
    case SinReposicion = "Sin Reposicion";
    case ReposicionParcial = "Reposicion Parcial";
    case ReposicionCompleta = "Reposicion Completa";
}
