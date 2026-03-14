<?php

namespace App\Shared\Enums\Kardex;

enum OrigenMovimiento: string
{
    /**
     * Ingresos
     */
    case NuevoLote = 'Nuevo lote';
    /**
     * Cuando se recepciona mas stock por
     * - una solicitud de reabastecimiento (el almacen pequeño recepciona del almacen principal)
     * - la recepcion de una orden de compra (el proveedor entrega al almacen - principal o pequeño)
     */
    case Recepcion = 'Recepcion';

    /**
     * Salidas
     */
    case Devolucion = 'Devolucion'; // se devuelve en diferentes procesos
    /**
     * Cuando se realiza una entrega por:
     * - un requerimiento de almacen (el almacen pequeño entrega al minero solicitante)
     * - una solicitud de reabastecimiento (el almacen principal entrega al almacen pequeño)
     */
    case Entrega = 'Entrega';

    /**
     * Mixtos
     */
    case AjusteStock = 'Ajuste de Stock'; // el almacenero edito manualmente el stock
}
