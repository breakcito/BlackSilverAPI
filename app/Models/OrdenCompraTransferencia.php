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
        'id_mina_destino', // la mina a la que se le transfiere el activo fijo
        'id_orden_compra_recepcion', // la recepcion de la orden de compra
        'id_empleado_transferencia', // quien registra la transferencia
        'id_empleado_recibe', // empleado quien recibe los productos - solo cuando es medio propio
        'id_proveedor_transporte', // proveedor encargado de llevar los productos
        'id_agencia_transporte', // agencia encargada de llevar los productos
        'id_lote_mineral', // Si es por terceros o agencia - util para tomar en cuesta ese costo en la produccion de un lote de mineral
        //
        'correlativo', // TRN | anual | por almacen de destino
        'numero_correlativo',
        'medio_entrega', // Terceros (Proveedores de Transporte) / Agencia / Propio
        // Si es por terceros o por agencia
        'numero_factura',
        'serie_factura',
        'serie_guia_transportista',
        'numero_guia_transportista',
        // Si es por terceros o por medio propio
        'serie_guia_remitente',
        'numero_guia_remitente',
        // Si es por terceros o agencia
        'costo_envio',
        //
        'observacion',
        'fecha_hora_transferencia',
        'evidencias',
        //
        'created_at',
        'estado', // Despacahdo / Recepcion completa / Recepcionado parcialmente
    ];


    /**
     * Crea un registro de transferencia de orden de compra.
     * 
     * @param array|null $evidencias Listado de archivos de evidencias guardados
     */
    public static function crear_transferencia(
        ?int $id_almacen_destino,
        int $id_orden_compra_recepcion,
        int $id_empleado_transferencia,
        ?int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        $evidencias = null,
        ?string $fecha_hora_transferencia = null,
        ?string $observacion = null,
        ?int $id_mina_destino = null,
        ?EstadoOCTransferencia $estado = null,
        ?string $medio_entrega = null,
        ?int $id_proveedor_transporte = null,
        ?int $id_agencia_transporte = null,
        ?string $numero_factura = null,
        ?string $serie_factura = null,
        ?string $serie_guia_transportista = null,
        ?string $numero_guia_transportista = null,
        ?string $serie_guia_remitente = null,
        ?string $numero_guia_remitente = null,
        ?float $costo_envio = null
    ) {
        $estadoVal = $estado ? $estado->value : ($id_mina_destino !== null ? EstadoOCTransferencia::RecepcionCompleta->value : EstadoOCTransferencia::EnDespacho->value);

        return self::insertGetId([
            'id_orden_compra_recepcion' => $id_orden_compra_recepcion,
            'id_almacen_destino' => $id_almacen_destino,
            'id_mina_destino' => $id_mina_destino,
            'id_empleado_transferencia' => $id_empleado_transferencia,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_transferencia' => $fecha_hora_transferencia ?? now(),
            'observacion' => $observacion,
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'medio_entrega' => $medio_entrega,
            'id_proveedor_transporte' => $id_proveedor_transporte,
            'id_agencia_transporte' => $id_agencia_transporte,
            'numero_factura' => $numero_factura,
            'serie_factura' => $serie_factura,
            'serie_guia_transportista' => $serie_guia_transportista,
            'numero_guia_transportista' => $numero_guia_transportista,
            'serie_guia_remitente' => $serie_guia_remitente,
            'numero_guia_remitente' => $numero_guia_remitente,
            'costo_envio' => $costo_envio,
            'created_at' => now(),
            'estado' => $estadoVal,
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
        ?int $yearcito = null,
        ?int $id_mina_destino = null
    ) {
        $sql = '
        SELECT
            trn.id AS id_transferencia,
            trn.correlativo,
            -- 
            oc.correlativo as codigo_orden_compra,
            oc.es_auditable,
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
            trn.id_mina_destino,
            mna_dest.nombre as mina_destino,
            -- 
            CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_transferencia,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(emp_rec.nombre), ""), NULLIF(TRIM(emp_rec.apellido), ""))) AS empleado_recibe,
            --
            trn.id_empleado_recibe,
            trn.id_proveedor_transporte,
            prov_t.razon_social as proveedor_transporte,
            trn.id_agencia_transporte,
            age_t.razon_social as agencia_transporte,
            trn.medio_entrega,
            trn.numero_factura,
            trn.serie_factura,
            trn.serie_guia_transportista,
            trn.numero_guia_transportista,
            trn.serie_guia_remitente,
            trn.numero_guia_remitente,
            trn.costo_envio,
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
        LEFT JOIN almacen alm_dest on alm_dest.id = trn.id_almacen_destino
        LEFT JOIN mina mna_dest on mna_dest.id = trn.id_mina_destino
        INNER JOIN empleado emp_ent ON emp_ent.id = trn.id_empleado_transferencia
        LEFT JOIN empleado emp_rec ON emp_rec.id = trn.id_empleado_recibe
        LEFT JOIN proveedor prov_t ON prov_t.id = trn.id_proveedor_transporte
        LEFT JOIN agencia_transporte age_t ON age_t.id = trn.id_agencia_transporte
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

        if ($id_mina_destino !== null) {
            $sql .= ' AND trn.id_mina_destino = :id_mina_destino';
            $params['id_mina_destino'] = $id_mina_destino;
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
