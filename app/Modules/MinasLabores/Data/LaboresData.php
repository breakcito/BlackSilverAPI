<?php

namespace App\Modules\MinasLabores\Data;

use App\Models\Labor;
use App\Models\TipoLabor;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Periodo;
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
        SELECT
            lb.id AS id_labor,
            em.razon_social AS empresa,
            em.url_logo AS url_logo_empresa,
            tp.nombre AS tipo_labor,
            tp.es_de_produccion,
            lb.nombre,
            lb.prefijo,
            lb.descripcion,
            lb.tipo_sostenimiento,
            lb.veta,
            lb.ancho,
            lb.alto,
            lb.nivel,
            lb.fecha_inicio,
            lb.fecha_fin_estimada,
            lb.fecha_cierre,
            lb.created_at,
            lb.estado
        FROM
            labor lb
        INNER JOIN empresa em ON
            em.id = lb.id_empresa
        LEFT JOIN tipo_labor tp ON
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

    public static function get_codigo_tipo_labor(int $id_tipo_labor): string
    {
        return TipoLabor::where('id', $id_tipo_labor)->value('codigo') ?? 'LAB';
    }

    public static function get_nuevo_correlativo(int $id_mina, int $id_empresa, int $id_tipo_labor, string $prefijo)
    {
        return CorrelativoHelper::generar(
            tabla: 'labor',
            prefijo: $prefijo,
            filtros: [
                'id_mina' => $id_mina,
                'id_empresa' => $id_empresa,
                'id_tipo_labor' => $id_tipo_labor
            ],
            longitudCeros: 3,
            reseteo: Periodo::Anual
        );
    }

    public static function get_labor_by_id(int $id_labor)
    {
        return self::get_historial_labores(id_labor: $id_labor);
    }

    public static function crear_labor(
        int $id_mina,
        int $id_empresa,
        ?int $id_tipo_labor,
        string $nombre,
        string $prefijo,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta,
        ?float $ancho,
        ?float $alto,
        ?string $nivel,
        ?string $fecha_inicio,
        ?string $fecha_fin_estimada = null,
    ) {
        return Labor::insertGetId([
            'id_mina' => $id_mina,
            'id_empresa' => $id_empresa,
            'id_tipo_labor' => $id_tipo_labor,
            'nombre' => $nombre,
            'prefijo' => $prefijo,
            'descripcion' => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta' => $veta,
            'ancho' => $ancho,
            'alto' => $alto,
            'nivel' => $nivel,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin_estimada' => $fecha_fin_estimada,
            'estado' => EstadoBase::Activo->value,
            'created_at' => now(),
        ]);
    }

    public static function finalizar_labor(int $id_labor, string $fecha_cierre)
    {
        return Labor::where('id', $id_labor)->update([
            'fecha_cierre' => $fecha_cierre,
            'estado' => EstadoBase::Inactivo->value,
        ]);
    }
}
