<?php

namespace App\Views\MinasLabores\Data;

use App\Models\Labor;
use App\Models\TipoLabor;
use App\Shared\Enums\EstadoBase;
use App\Shared\Enums\Periodo;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class LaboresData
{
    /**
     * Lista de tipos de labores
     */
    public static function get_tipos_labor()
    {
        $sql = '
        SELECT
            tp.id AS id_tipo_labor,
            tp.nombre,
            tp.es_de_produccion
        FROM
            tipo_labor tp
        ';

        return DB::select($sql);
    }

    /**
     * Historial de labores de la mina
     */
    public static function get_historial_labores(?int $id_mina = null, ?int $id_labor = null)
    {
        $sql = '
        SELECT DISTINCT
            lb.id AS id_labor,
            em.razon_social AS empresa,
            em.path_logo AS path_logo_empresa,
            tp.nombre AS tipo_labor,
            tp.es_de_produccion,
            lb.correlativo,
            lb.nombre,
            lb.descripcion,
            lb.tipo_sostenimiento,
            lb.veta,
            lb.ancho,
            lb.alto,
            lb.nivel,
            lb.fecha_inicio,
            lb.fecha_fin,
            lb.created_at,
            lb.estado
        FROM
            labor lb
        INNER JOIN empresa em ON
            em.id = lb.id_empresa
        INNER JOIN tipo_labor tp ON
            tp.id = lb.id_tipo_labor
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_labor !== null) {
            $sql .= ' AND lb.id = :id_labor';
            $params['id_labor'] = $id_labor;

            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND lb.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY lb.created_at DESC';

        return DB::select($sql, $params);
    }

    public static function get_codigo_tipo_labor(int $id_tipo_labor)
    {
        return TipoLabor::where('id', $id_tipo_labor)->first(["codigo"]);
    }

    public static function get_nuevo_correlativo(int $id_mina, string $prefijo)
    {
        return CorrelativoHelper::generar(
            tabla: 'labor',
            prefijo: $prefijo,
            filtros: ['id_mina' => $id_mina],
            longitudCeros: 5,
            reseteo: Periodo::Ninguno
        );
    }

    public static function get_labor_by_id(int $id_labor)
    {
        return self::get_historial_labores(id_labor: $id_labor);
    }

    public static function crear_labor(
        int $id_mina,
        int $id_empresa,
        int $id_tipo_labor,
        string $nombre,
        string $correlativo,
        int $numero_correlativo,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta,
        ?float $ancho,
        ?float $alto,
        ?string $nivel,
        ?string $fecha_inicio,
    ) {
        return Labor::insertGetId([
            'id_mina' => $id_mina,
            'id_empresa' => $id_empresa,
            'id_tipo_labor' => $id_tipo_labor,
            'nombre' => $nombre,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'descripcion' => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta' => $veta,
            'ancho' => $ancho,
            'alto' => $alto,
            'nivel' => $nivel,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
