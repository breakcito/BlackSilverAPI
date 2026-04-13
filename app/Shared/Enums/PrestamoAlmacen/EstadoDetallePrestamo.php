<?php

namespace App\Shared\Enums\PrestamoAlmacen;

enum EstadoDetallePrestamo: string
{
    case EsperandoAprobacion = 'Esperando Aprobación';
    case Rechazado = 'Rechazado';
    case Aprobado = 'Aprobado';
    case EnDespacho = 'En Despacho';
    case Cerrado = 'Cerrado';
    case Completado = 'Completado';

        // Para trazabilidad específica (Log)
    case NuevaEntrega = 'Nueva Entrega';
    case EnReposicion = 'En Reposición';

    /**
     * Obtiene la glosa estándar para la trazabilidad
     */
    public function getGlosa(?string $dinamico = null): string
    {
        return match ($this) {
            self::EsperandoAprobacion => 'Esperando aprobación del almacén',
            self::Aprobado => 'El encargado del almacén aprobó el préstamo de este producto',
            self::EnDespacho => 'El almacén está procesando el despacho de este producto',
            self::NuevaEntrega => "Se registró una entrega de {$dinamico} producto(s)",
            self::Completado => 'La atención de este ítem ha sido finalizada al 100%',
            self::Rechazado => 'Lo sentimos, el préstamo no pudo ser atendido por el almacén',
            self::EnReposicion => "Se ha registrado la reposición de {$dinamico} producto(s)",
            self::Cerrado => 'El seguimiento de este ítem ha sido finalizado.',
        };
    }
}
