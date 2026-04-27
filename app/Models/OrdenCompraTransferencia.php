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
        ?string $fecha_hora_transferencia = null,
        ?string $observacion = null,
        $evidencias = null
    ) {
        return self::insertGetId([
            'id_orden_compra_recepcion' => $id_orden_compra_recepcion,
            'id_almacen_destino' => $id_almacen_destino,
            'id_empleado_transferencia' => $id_empleado_transferencia,
            'id_personal_recibe' => $id_personal_recibe,
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
    ) {
        $sql = '
        SELECT
            trn.id AS id_transferencia,
            trn.id_orden_compra_recepcion,
            --
            trn.id_almacen_destino,
            alm.nombre as almacen_destino,
            alm.es_principal as es_para_un_almacen_principal,
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
        INNER JOIN empleado emp_ent ON emp_ent.id = trn.id_empleado_transferencia
        INNER JOIN personal_externo per_rec ON per_rec.id = trn.id_personal_recibe
        INNER JOIN almacen alm on alm.id = trn.id_almacen_destino
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_transferencia) {
            $sql .= ' AND trn.id = :id_transferencia';
            $params['id_transferencia'] = $id_transferencia;
            return DB::selectOne($sql, $params);
        }

        if ($id_orden_compra_recepcion) {
            $sql .= ' AND trn.id_orden_compra_recepcion = :id_orden_compra_recepcion';
            $params['id_orden_compra_recepcion'] = $id_orden_compra_recepcion;
        }

        if ($id_almacen_destino) {
            $sql .= ' AND trn.id_almacen_destino = :id_almacen_destino';
            $params['id_almacen_destino'] = $id_almacen_destino;
        }

        $sql .= ' ORDER BY trn.created_at DESC;';
        return DB::select($sql, $params);
    }
}
