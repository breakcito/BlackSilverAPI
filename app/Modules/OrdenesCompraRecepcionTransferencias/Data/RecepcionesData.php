<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Data;

use App\Models\OrdenCompraTransferenciaRecepcion;
use App\Models\OrdenCompraTransferenciaRecepcionDetalle;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\OrdenCompra\EstadoOCTransRecepcion;
use App\Shared\Helpers\CorrelativoHelper;

class RecepcionesData
{
    /**
     * ---------------------
     * PARA LA CABECERA
     * ---------------------
     */

    public static function get_nuevo_correlativo(int $id_transferencia)
    {
        return CorrelativoHelper::generar(
            tabla: 'orden_compra_transferencia_recepcion',
            prefijo: '',
            filtros: [
                'id_orden_compra_transferencia' => $id_transferencia
            ],
            reseteo: Periodo::Ninguno
        );
    }

    /**
     * Crear una cabecera de recepción de una transferencia
     */
    public static function crear_recepcion(
        int $id_transferencia,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia,
        EstadoOCTransRecepcion $estado
    ) {
        return OrdenCompraTransferenciaRecepcion::insertGetId([
            'id_orden_compra_transferencia' => $id_transferencia,
            'id_empleado_registro' => $id_empleado,
            'observacion' => $observacion,
            'fecha_hora_recepcion' => $fecha_hora_recepcion,
            'evidencias' => $evidencias,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    public static function get_recepciones(?int $id_recepcion = null, ?int $id_transferencia = null)
    {
        return OrdenCompraTransferenciaRecepcion::get_recepciones(
            id_recepcion: $id_recepcion,
            id_transferencia: $id_transferencia
        );
    }


    /**
     * ---------------------
     * PARA EL DETALLE
     * ---------------------
     */


    /**
     * Crear uno o varios detalles de recepción de una transferencia
     * detalles: [{
     *      int $id_detalle_transferencia,
     *      int $cantidad_recepcionada_base,
     *      EstadoOCTransRecepcionDetalle $estado
     * }]
     */
    public static function crear_recepcion_detalle(
        int $id_recepcion,
        array $detalles
    ) {

        $detalles_a_insertar = [];

        foreach ($detalles as $detalle) {
            $detalles_a_insertar[] = [
                'id_orden_compra_transferencia_recepcion' => $id_recepcion,
                'id_orden_compra_transferencia_detalle' => $detalle['id_detalle_transferencia'],
                'cantidad_recepcionada_base' => $detalle['cantidad_recepcionada_base'],
                'estado' => $detalle['estado']->value,
            ];
        }

        return OrdenCompraTransferenciaRecepcionDetalle::insert($detalles_a_insertar);
    }

    public static function get_detalles_recepcion(array|int $ids_recepciones)
    {
        return OrdenCompraTransferenciaRecepcionDetalle::get_detalles(ids_recepciones: $ids_recepciones);
    }
}
