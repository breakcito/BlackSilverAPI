<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoEntrega;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{
    /**
     * Crea un detalle de entrega (un lote de salida para un ítem del préstamo).
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_prestamo_detalle,
        int $id_lote_producto,
        float $cantidad,
        float $cantidad_base,
        ?string $comentario = null
    ): int {
        return PrestamoAlmacenEntregaDetalle::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_producto'              => $id_lote_producto,
            'cantidad'                    => $cantidad,
            'cantidad_base'               => $cantidad_base,
            'comentario'                  => $comentario,
            'estado'                      => EstadoPrestamoEntrega::EnDespacho->value,
        ]);
    }

    /**
     * Marca un detalle como recibido y vincula el lote de ingreso.
     */
    public static function marcar_como_recibido(int $id_entrega_detalle): bool
    {
        return (bool) PrestamoAlmacenEntregaDetalle::where('id', $id_entrega_detalle)
            ->update([
                'estado'          => EstadoPrestamoEntrega::RecepcionCompleta->value
            ]);
    }

    /**
     * Verifica si todos los detalles de la entrega están recibidos/anulados para cerrar la entrega (Status cabecera).
     */
    public static function verificar_y_completar_entrega(int $id_entrega): void
    {
        $pendientes = PrestamoAlmacenEntregaDetalle::where('id_prestamo_almacen_entrega', $id_entrega)
            ->where('estado', '!=', EstadoPrestamoEntrega::RecepcionCompleta->value)
            ->where('estado', '!=', EstadoPrestamoEntrega::Anulado->value)
            ->count();

        if ($pendientes === 0) {
            PrestamoAlmacenEntrega::where('id', $id_entrega)
                ->update(['estado' => EstadoPrestamoEntrega::RecepcionCompleta->value]);
        }
    }
}
