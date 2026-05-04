<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOCTransferencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraTransferencia extends Model
{
    protected $table = 'orden_compra_transferencia';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen_destino', // el almacen al que se transfiere el stock
        'id_orden_compra_recepcion', // la recepcion de la orden de compra
        'id_empleado_transferencia', // quien registra la transferencia
        'id_personal_recibe', // la persona que recibe los productos para el envio
        //
        'correlativo', // TRN | anual | por almacen de destino
        'numero_correlativo',
        //
        'observacion',
        'fecha_hora_transferencia',
        'evidencias',
        //
        'created_at',
        'estado', // Despacahdo / Recepcion completa / Recepcionado parcialmente
    ];


    public static function crear_transferencia(
        int $id_almacen_destino,
        int $id_orden_compra_recepcion,
        int $id_empleado_transferencia,
        int $id_personal_recibe,
        string $correlativo,
        int $numero_correlativo,
        ?string $fecha_hora_transferencia = null,
        ?string $observacion = null,
        $evidencias = null
    ) {
        return self::insertGetId([
            'id_orden_compra_recepcion' => $id_orden_compra_recepcion,
            'id_almacen_destino' => $id_almacen_destino,
            'id_empleado_transferencia' => $id_empleado_transferencia,
            'id_personal_recibe' => $id_personal_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_transferencia' => $fecha_hora_transferencia ?? now(),
            'observacion' => $observacion ?? '',
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'created_at' => now(),
            'estado' => EstadoOCTransferencia::EnDespacho->value,
        ]);
    }

    /**
     * Consulta generica para obtener el registro de una entrega por prestamo
     * o todo el historial de entregas de un prestamo
     */
    public static function get_transferencias(
        ?int $id_transferencia = null,
        ?int $id_orden_compra_recepcion = null,
        ?int $id_almacen_destino = null,
        ?int $mes = null,
        ?int $yearcito = null
    ) {
        $sql = '
        SELECT
            trn.id AS id_transferencia,
            trn.correlativo,
            -- 
            oc.correlativo as codigo_orden_compra,
            -- 
            -- de que recepcion de la orden de compra se hizo la transferencia
            trn.id_orden_compra_recepcion as id_recepcion,
            ocr.numero_correlativo as numero_recepcion,
            -- 
            -- de donde viene la transferencia
            alm.nombre as almacen_origen,
            alm.es_principal as desde_un_almacen_principal,
            -- 
            -- hacia donde va la transferencia
            trn.id_almacen_destino,
            alm_dest.nombre as almacen_destino,
            alm_dest.es_principal as es_para_un_almacen_principal,
            -- 
            CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_transferencia,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(per_rec.nombre), ""), NULLIF(TRIM(per_rec.apellido), ""))) AS personal_recibe,
            -- 
            trn.fecha_hora_transferencia,
            trn.observacion,
            trn.evidencias,
            -- 
            trn.created_at,
            trn.estado
        FROM
            orden_compra_transferencia trn
		INNER JOIN orden_compra_recepcion ocr on ocr.id = trn.id_orden_compra_recepcion
        INNER JOIN orden_compra oc on oc.id = ocr.id_orden_compra
        INNER JOIN almacen alm on alm.id = ocr.id_almacen_recepcionista
        INNER JOIN almacen alm_dest on alm_dest.id = trn.id_almacen_destino
        INNER JOIN empleado emp_ent ON emp_ent.id = trn.id_empleado_transferencia
        INNER JOIN personal_externo per_rec ON per_rec.id = trn.id_personal_recibe
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_transferencia !== null) {
            $sql .= ' AND trn.id = :id_transferencia';
            $params['id_transferencia'] = $id_transferencia;
            return DB::selectOne($sql, $params);
        }

        if ($id_orden_compra_recepcion !== null) {
            $sql .= ' AND trn.id_orden_compra_recepcion = :id_orden_compra_recepcion';
            $params['id_orden_compra_recepcion'] = $id_orden_compra_recepcion;
        }

        if ($id_almacen_destino !== null) {
            $sql .= ' AND trn.id_almacen_destino = :id_almacen_destino';
            $params['id_almacen_destino'] = $id_almacen_destino;
        }

        if ($mes !== null) {
            $sql .= ' AND MONTH(trn.fecha_hora_transferencia) = :mes';
            $params['mes'] = $mes;
        }

        if ($yearcito !== null) {
            $sql .= ' AND YEAR(trn.fecha_hora_transferencia) = :yearcito';
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY trn.created_at DESC;';
        return DB::select($sql, $params);
    }
}
