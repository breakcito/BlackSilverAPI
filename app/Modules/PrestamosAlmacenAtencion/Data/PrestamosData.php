<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacen;
use Illuminate\Support\Facades\DB;

class PrestamosData
{
    /**
     * Obtiene los préstamos recibidos por un almacén (como prestamista).
     * Filtra por mes/año y estado si se proporcionan.
     */
    public static function get_prestamos_por_almacen(int $id_almacen, string $mes, string $yearcito): array
    {
        return PrestamoAlmacen::get_prestamos(
            id_almacen_prestamista: $id_almacen,
            mes: (int) $mes,
            yearcito: (int) $yearcito,
        );
    }

    /**
     * Obtiene la solicitud de reabastecimiento vinculada a un préstamo.
     */
    public static function get_id_solicitud_by_prestamo(int $id_prestamo)
    {
        return PrestamoAlmacen::select('id_solicitud_reabastecimiento')
            ->where('id', $id_prestamo)
            ->first();
    }

    /**
     * Obtiene información del almacén solicitante de un préstamo.
     */
    public static function get_almacen_solicitante_by_id(int $id_prestamo)
    {
        return PrestamoAlmacen::from('prestamo_almacen as pa')
            ->join('solicitud_reabastecimiento as sr', 'sr.id', '=', 'pa.id_solicitud_reabastecimiento')
            ->join('almacen as alm', 'alm.id', '=', 'sr.id_almacen_solicitante')
            ->select('alm.nombre')
            ->where('pa.id', $id_prestamo)
            ->first();
    }

    /**
     * Obtiene la cabecera de un préstamo.
     */
    public static function get_prestamo_header_by_id(int $id_prestamo)
    {
        return PrestamoAlmacen::where('id', $id_prestamo)
            ->first();
    }
    /**
     * Obtiene el correlativo de un préstamo.
     */
    public static function get_correlativo(int $id_prestamo): ?string
    {
        return PrestamoAlmacen::where('id', $id_prestamo)
            ->value('correlativo');
    }
}
