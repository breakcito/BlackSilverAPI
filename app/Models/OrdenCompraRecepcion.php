<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraRecepcion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraRecepcion extends Model
{
    protected $table = 'orden_compra_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra',
        'id_almacen_recepcionista',
        'id_empleado_recepcion',
        //
        'numero_correlativo', // indica si es la primera, segunda, etc recepcion de la orden de compra
        'observacion',
        'fecha_hora_recepcion',
        'serie_guia_remision',
        'numero_guia_remision',
        //
        'con_incidencia', // 1|0, si es 1, debe subir obligatoriamente evidencias y escribir una observacion
        'evidencias',
        //
        'created_at',
        'estado', // Recepcionado Parcialmente / Recepcionado
    ];

    public static function crear_recepcion(
        int $id_orden_compra,
        int $id_almacen_recepcionista,
        int $id_empleado_recepcion,
        int $numero_correlativo,
        ?string $observacion = null,
        ?string $fecha_hora_recepcion = null,
        ?string $serie_guia_remision = null,
        ?string $numero_guia_remision = null,
        ?bool $con_incidencia = false,
        ?string $evidencias = null,
        ?EstadoOrdenCompraRecepcion $estado = EstadoOrdenCompraRecepcion::RecepcionadoParcialmente
    ): int {
        return self::insertGetId([
            'id_orden_compra' => $id_orden_compra,
            'id_almacen_recepcionista' => $id_almacen_recepcionista,
            'id_empleado_recepcion' => $id_empleado_recepcion,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'fecha_hora_recepcion' => $fecha_hora_recepcion ?? now(),
            'serie_guia_remision' => $serie_guia_remision,
            'numero_guia_remision' => $numero_guia_remision,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'evidencias' => $evidencias,
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    public static function get_recepciones(?int $id_recepcion = null, ?int $id_orden_compra = null)
    {
        $sql = '
        SELECT
            r.id as id_recepcion,
            r.id_orden_compra,
            -- 
            -- indica si es la primera, segunda, etc recepcion de la orden de compra
            r.numero_correlativo,
            -- 
            -- el almacen que recepciona los productos
            r.id_almacen_recepcionista,
            alm.nombre as almacen_recepcionista,
            alm.es_principal as para_un_almacen_principal,
            -- 
            -- un empleado responsable del almacen que recepciona los productos
            CONCAT(e.nombre, " ", e.apellido) AS empleado_recepcion,
            -- 
            r.observacion,
            r.fecha_hora_recepcion,
            CONCAT(r.serie_guia_remision, "-", r.numero_guia_remision) as guia_remision,
            r.con_incidencia,
            r.evidencias,
            -- 
            r.created_at,
            r.estado
        FROM
            orden_compra_recepcion r
        INNER JOIN almacen alm on alm.id = r.id_almacen_recepcionista
        INNER JOIN empleado e ON e.id = r.id_empleado_recepcion
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_recepcion !== null) {
            $sql .= " AND r.id = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
            return DB::selectOne($sql, $params);
        }

        if ($id_orden_compra !== null) {
            $sql .= " AND r.id_orden_compra = :id_orden_compra";
            $params['id_orden_compra'] = $id_orden_compra;
        }

        $sql .= " ORDER BY r.numero_correlativo DESC";

        return DB::select($sql, $params);
    }
}
