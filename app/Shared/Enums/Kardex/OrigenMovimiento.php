<?php

namespace App\Shared\Enums\Kardex;

enum OrigenMovimiento: string
{
    /**
     * -------------------------------------
     * Ingresos
     * -------------------------------------
     */

    /**
     * Cuando se registra un lote manualmente
     */
    case NuevoLote = 'Nuevo Lote';

    /**
     * Cuando se recepciona mas stock por
     * - una solicitud de reabastecimiento (el almacen pequeño recepciona del almacen principal)
     * - la recepcion de una orden de compra (el proveedor entrega al almacen - principal o pequeño)
     */
    case Recepcion = 'Recepcion';

    /**
     * -------------------------------------
     * Salidas
     * -------------------------------------
     */

    /**
     * Cuando se realiza una entrega por:
     * - un requerimiento de almacen (el almacen pequeño entrega al minero solicitante)
     * - una solicitud de reabastecimiento (el almacen principal entrega al almacen pequeño)
     * - un prestamo de almacen (el almacen principal entrega al almacen pequeño)
     */
    case Entrega = 'Entrega';

    /**
     * Cuando se realiza una reposicion, tipicamente luego de que logistica
     * reponga stock a un almacen que previamente le presto a otro que lo necesitaba
     */
    case Reposicion = 'Reposición';

    /**
     * -------------------------------------
     * Mixtos
     * -------------------------------------
     */

    /**
     * Cuando se realiza un ajuste de stock, ya sea manual o automatico
     */
    case AjusteStock = 'Ajuste de Stock';
}
