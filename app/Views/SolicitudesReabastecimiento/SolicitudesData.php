<?php

namespace App\Views\SolicitudesReabastecimiento;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\UnidadMedida;
use App\Shared\Enums\SolicitudReabastecimiento\SolicitudDetalleEstadoEnum;
use App\Shared\Enums\SolicitudReabastecimiento\SolicitudEstadoEnum;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudesData extends Model
{
    // Obtener una o toda la lista de solicitudes
    public static function get_solicitudes(
        ?int $id_almacen_solicitante = null,
        ?int $id_solicitud = null,
        ?int $mes = null,
        ?int $yearcito = null,
    ) {
        $sql = "
        SELECT
            sr.id AS id_solicitud_reabastecimiento,
            sr.id_almacen_solicitante,
            alm.nombre AS almacen_solicitante,
            CONCAT(em.nombre, ' ', em.apellido) AS empleado_solicitante,
            sr.correlativo,
            sr.premura,
            sr.fecha_hora_entrega_requerida,
            sr.created_at,
            sr.estado
        FROM
            solicitud_reabastecimiento sr
        INNER JOIN empleado em ON
            em.id = sr.id_empleado_solicitante
        INNER JOIN almacen alm ON
            alm.id = sr.id_almacen_solicitante
        WHERE
            1 = 1
        ";

        $params = [];

        // Si se busca por id, devolvemos solo ese registro
        if ($id_solicitud !== null) {
            $sql .= ' AND sr.id = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen_solicitante !== null) {
            $sql .= ' AND sr.id_almacen_solicitante = :id_almacen_solicitante';
            $params['id_almacen_solicitante'] = $id_almacen_solicitante;
        }

        // Por periodo
        if ($mes !== null) {
            $sql .= ' AND MONTH(sr.created_at) = :mes';
            $params['mes'] = $mes;
        }

        if ($yearcito !== null) {
            $sql .= ' AND YEAR(sr.created_at) = :yearcito';
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY sr.created_at DESC';

        return DB::select($sql, $params);
    }

    // Obtener una solicitud
    public static function get_solicitud_by_id(int $id_solicitud)
    {
        return self::get_solicitudes(id_solicitud: $id_solicitud);
    }

    // Obtener el detalle de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = '
        SELECT
            srd.id AS id_solicitud_reabastecimiento_detalle,
            pr.nombre as producto, -- manzana
            uni_p.abreviatura as unidad_medida_base_abreviatura, -- kilo
            uni_s.abreviatura as unidad_medida_solicitud_abreviatura, -- caja
            pr.es_fiscalizado,
            pr.es_perecible,
            srd.cantidad_solicitada, -- 2 cajas
            srd.contenido_por_presentacion, -- 10 kilos
            srd.cantidad_solicitada_base, -- 20 kilos
            srd.cantidad_entregada, -- 1/2 caja
            srd.cantidad_entregada_base, -- 5 kilos
            srd.comentario,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        INNER JOIN producto pr ON
            pr.id = srd.id_producto
        INNER JOIN unidad_medida uni_s ON
            uni_s.id = srd.id_unidad_medida
        INNER JOIN unidad_medida uni_p ON
            uni_p.id = pr.id_unidad_medida_base
        WHERE srd.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento
        ';

        return DB::select($sql, ['id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento]);
    }

    // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.nombre,
            uni.id_unidad_medida,
            uni.abreviatura as unidad_medida_abreviatura
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE 
            pr.estado = "Activo"
        ';

        return DB::select($sql);
    }

    // Obtener la lista de almacenes en las que el empleado
    // solicitante es reesponsable
    public static function get_almacenes(int $id_empleado)
    {
        $sql = '
        SELECT DISTINCT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN responsable_almacen res ON
            res.id_almacen = alm.id
        WHERE
            alm.es_principal != 1 AND -- que no sea un almacen principal
            res.id_empleado = :id_empleado AND -- donde el empleado sea responsable
            res.estado = "Activo" -- y su responsabilidad siga vigente
        ';

        return DB::select($sql, ["id_empleado" => $id_empleado]);
    }

    // Listar unidades de medida.
    public static function get_unidades_medida()
    {
        return UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura', 'es_base')
            ->orderBy('nombre', 'asc');
    }

    // Funcion helpder que ayuda a crear la cabecera de la solicitud
    public function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        string $observacion,
        string $premura,
        string $fecha_entrega_requerida,
    ) {
        return SolicitudReabastecimiento::insertGetId([
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'premura' => $premura,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => SolicitudEstadoEnum::Generada->value,
        ]);
    }

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle_solicitud(
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario
    ) {
        return SolicitudReabastecimientoDetalle::insert([
            'id_solicitud_reabastecimiento' => $id_solicitud,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad_solicitada,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_solicitada_base' => $cantidad_solicitada_base,
            'cantidad_entregada' => 0,
            'cantidad_entregada_base' => 0,
            'comentario' => $comentario,
            'estado' => SolicitudDetalleEstadoEnum::EsperandoAprobacion->value,
        ]);
    }

    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return CorrelativoHelper::generar(
            'solicitud_reabastecimiento',
            'SRA',
            ["id_almacen_solicitante" => $id_almacen_solicitante]
        );
    }
}
