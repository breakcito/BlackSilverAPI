<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Data;

use App\Models\OrdenCompraTransferenciaRecepcion;
use App\Models\OrdenCompraTransferenciaRecepcionDetalle;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\OrdenCompra\EstadoOCTransRecepcion;
use App\Shared\Helpers\CorrelativoHelper;

class RecepcionesData
{
    // -----------------------------------------------
    // CABECERA
    // -----------------------------------------------

    public static function get_nuevo_correlativo(int $id_transferencia): int
    {
        $max = OrdenCompraTransferenciaRecepcion::where('id_orden_compra_transferencia', $id_transferencia)
            ->max('numero_correlativo');
        return ($max ?? 0) + 1;
    }

    public static function crear_recepcion(
        int $id_transferencia,
        int $id_empleado,
        int $numero_correlativo,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia,
        EstadoOCTransRecepcion $estado
    ): int {
        return OrdenCompraTransferenciaRecepcion::insertGetId([
            'id_orden_compra_transferencia' => $id_transferencia,
            'id_empleado_registro' => $id_empleado,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'fecha_hora_recepcion' => $fecha_hora_recepcion,
            'evidencias' => $evidencias,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    public static function get_recepciones(?int $id_recepcion = null, ?int $id_transferencia = null): array
    {
        return OrdenCompraTransferenciaRecepcion::get_recepciones(
            id_recepcion: $id_recepcion,
            id_transferencia: $id_transferencia
        );
    }

    public static function update_estado_recepcion(int $id_recepcion, EstadoOCTransRecepcion $estado): void
    {
        OrdenCompraTransferenciaRecepcion::where('id', $id_recepcion)
            ->update(['estado' => $estado->value]);
    }

    // -----------------------------------------------
    // DETALLE
    // -----------------------------------------------

    /**
     * Crea uno o varios detalles de recepción.
     * $detalles: [{ id_detalle_transferencia, cantidad_recepcionada_base, estado }]
     */
    public static function crear_recepcion_detalle(int $id_recepcion, array $detalles): void
    {
        $rows = [];
        foreach ($detalles as $det) {
            $rows[] = [
                'id_orden_compra_transferencia_recepcion' => $id_recepcion,
                'id_orden_compra_transferencia_detalle' => $det['id_detalle_transferencia'],
                'cantidad_recepcionada_base' => $det['cantidad_recepcionada_base'],
                'estado' => $det['estado']->value,
            ];
        }
        OrdenCompraTransferenciaRecepcionDetalle::insert($rows);
    }

    /**
     * Obtiene los detalles de una o varias recepciones.
     */
    public static function get_detalles_recepcion(array|int $ids_recepciones): array
    {
        return OrdenCompraTransferenciaRecepcionDetalle::get_detalles(
            ids_recepciones: $ids_recepciones
        );
    }

    /**
     * Suma la cantidad ya recepcionada (en base) para un detalle de transferencia.
     * Necesario para determinar si la recepción es parcial o completa.
     */
    public static function get_cantidad_recepcionada_acumulada(int $id_detalle_transferencia): float
    {
        return (float) OrdenCompraTransferenciaRecepcionDetalle::where(
            'id_orden_compra_transferencia_detalle', $id_detalle_transferencia
        )->sum('cantidad_recepcionada_base');
    }

    /**
     * Alias explícito para claridad en RecepcionesService.
     */
    public static function get_cantidad_recepcionada_acumulada_por_transferencia_detalle(
        int $id_detalle_transferencia
    ): float {
        return self::get_cantidad_recepcionada_acumulada($id_detalle_transferencia);
    }
}
