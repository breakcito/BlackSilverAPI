<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenEntrega extends Model
{
    protected $table = 'prestamo_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_empleado_entrega', // quien registra la entrega
        'id_empleado_recibe', // empleado quien recibe los productos - solo cuando es medio propio
        'id_proveedor_transporte', // proveedor encargado de llevar los productos
        'id_agencia_transporte', // agencia encargada de llevar los productos
        'id_lote_mineral', // Si es por terceros o agencia - util para tomar en cuesta ese costo en la produccion de un lote de mineral
        //
        'correlativo',
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
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];

    /**
     * Consulta generica para obtener el registro de una entrega por prestamo
     * o todo el historial de entregas de un prestamo
     */
    public static function get_entregas(
        ?int $id_entrega = null,
        ?int $id_prestamo = null,
        ?int $id_solicitud_reabastecimiento = null
    ) {
        $sql = '
        SELECT
            pae.id AS id_prestamo_entrega,
            pae.id_prestamo_almacen,
            pa.id_solicitud_reabastecimiento,
            --
            pa.id_almacen_prestamista as id_almacen_entrega,
            alm.nombre as almacen_entrega,
            --
            CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_entrega,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(emp_rec.nombre), ""), NULLIF(TRIM(emp_rec.apellido), ""))) AS empleado_recibe,
            --
            pae.id_empleado_recibe,
            pae.id_proveedor_transporte,
            prov_t.razon_social as proveedor_transporte,
            pae.id_agencia_transporte,
            age_t.razon_social as agencia_transporte,
            pae.medio_entrega,
            pae.numero_factura,
            pae.serie_factura,
            pae.serie_guia_transportista,
            pae.numero_guia_transportista,
            pae.serie_guia_remitente,
            pae.numero_guia_remitente,
            pae.costo_envio,
            --
            pae.correlativo,
            pae.fecha_hora_entrega,
            pae.observacion,
            pae.evidencias,
            pae.created_at,
            pae.estado
        FROM
            prestamo_almacen_entrega pae
        INNER JOIN empleado emp_ent ON emp_ent.id = pae.id_empleado_entrega
        LEFT JOIN empleado emp_rec ON emp_rec.id = pae.id_empleado_recibe
        LEFT JOIN proveedor prov_t ON prov_t.id = pae.id_proveedor_transporte
        LEFT JOIN agencia_transporte age_t ON age_t.id = pae.id_agencia_transporte
        INNER JOIN prestamo_almacen pa ON pa.id = pae.id_prestamo_almacen
        INNER JOIN almacen alm on alm.id = pa.id_almacen_prestamista
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_entrega) {
            $sql .= ' AND pae.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_solicitud_reabastecimiento) {
            $sql .= ' AND pa.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud_reabastecimiento;
        }

        if ($id_prestamo) {
            $sql .= ' AND pae.id_prestamo_almacen = :id_prestamo';
            $params['id_prestamo'] = $id_prestamo;
        }

        $sql .= ' ORDER BY pae.created_at DESC;';
        return DB::select($sql, $params);
    }
}
