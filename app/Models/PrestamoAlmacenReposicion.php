<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicion;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla que presenta las reposiciones que realiza logistica
 * a los almacenes que fueron prestamistas, con el fin
 * de reponer el stock entregado.
 */
class PrestamoAlmacenReposicion extends Model
{
    protected $table = 'prestamo_almacen_reposicion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen', // el prestamo que se esta reponiendo
        'id_almacen_entrega', // uno de los almacenes principales
        'id_empleado_entrega', // empleado que hace la reposicion
        'id_empleado_recibe', // empleado quien recibe los productos - solo cuando es medio propio
        'id_proveedor_transporte', // proveedor encargado de llevar los productos
        'id_agencia_transporte', // agencia encargada de llevar los productos
        'id_lote_mineral', // Si es por terceros o agencia - util para tomar en cuesta ese costo en la produccion de un lote de mineral
        //
        'correlativo', // prefijo: RPS
        'numero_correlativo',
        //
        'medio_entrega', // Terceros (Proveedores de Transporte) / Agencia / Propio | Enum de MedioEntrega
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
        'fecha_hora_reposicion', // fecha y hora que el usuario fija en la ui
        'evidencias',
        'created_at', // fecha y hora de registro en el sistema
        'estado', // En Despacho / Recepcionado
    ];

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_reposicion',
            prefijo: 'RPS',
            columnaFecha: 'fecha_hora_reposicion'
        );
    }

    /**
     * Metodos de ayuda para registrar una reposicion por prestamo
     */
    public static function crear_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_reposicion,
        ?string $observacion = null,
        $evidencias = null
    ) {
        return self::insertGetId([
            'id_prestamo_almacen' => $id_prestamo_almacen,
            'id_almacen_entrega' => $id_almacen_entrega,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_reposicion' => $fecha_hora_reposicion,
            'observacion' => $observacion ?? '',
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'estado' => EstadoPrestamoReposicion::EnDespacho->value,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener una reposicion o el historial de reposiciones de un préstamo
     */
    public static function get_reposiciones(
        ?int $id_reposicion = null,
        ?int $id_prestamo_almacen = null
    ) {
        $sql = '
        SELECT 
            r.id as id_reposicion,
            r.id_prestamo_almacen,
            --
            r.id_almacen_entrega,
            a.nombre AS almacen_entrega,
            --
            r.correlativo,
            r.fecha_hora_reposicion,
            r.observacion,
            r.evidencias,
            CONCAT(e.nombre, " ", e.apellido) AS registrado_por,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(emp_rec.nombre), ""), NULLIF(TRIM(emp_rec.apellido), ""))) AS empleado_recibe,
            r.created_at,
            r.estado
        FROM 
            prestamo_almacen_reposicion r
        INNER JOIN almacen a ON a.id = r.id_almacen_entrega
        INNER JOIN empleado e ON e.id = r.id_empleado_entrega
        INNER JOIN empleado emp_rec ON emp_rec.id = r.id_empleado_recibe
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_reposicion) {
            $sql .= ' AND r.id = :id_reposicion';
            $params['id_reposicion'] = $id_reposicion;
            return DB::selectOne($sql, $params);
        }

        if ($id_prestamo_almacen) {
            $sql .= ' AND r.id_prestamo_almacen = :id_prestamo_almacen';
            $params['id_prestamo_almacen'] = $id_prestamo_almacen;
        }

        $sql .= ' ORDER BY r.fecha_hora_reposicion DESC;';
        return DB::select($sql, $params);
    }
}
