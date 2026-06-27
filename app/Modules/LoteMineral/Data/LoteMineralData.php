<?php

namespace App\Modules\LoteMineral\Data;

use App\Models\LoteMineral;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Enums\_Generic\Periodo;
use Illuminate\Support\Facades\DB;

class LoteMineralData
{
    /**
     * Listar lotes de mineral con filtros opcionales.
     */
    public static function get_lotes(?int $mes = null, ?int $anio = null)
    {
        $query = DB::table('lote_mineral as lm')
            ->select([
                'lm.id as id_lote_mineral',
                'lm.correlativo',
                'lm.codigo_interno',
                'lm.fecha_inicio_produccion',
                'lm.descripcion',
                'lm.estado',
                'lm.created_at',
                'c.id as id_contratista',
                DB::raw("CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as contratista"),
                'm.id as id_mina',
                'm.nombre as mina',
                'l.id as id_labor',
                'l.nombre as labor',
                'l.prefijo as labor_prefijo',
                'e.id as id_empleado_registro',
                DB::raw("CONCAT(e.nombre, ' ', COALESCE(e.apellido, '')) as empleado_registro"),
            ])
            ->join('empleado as c', 'lm.id_contratista', '=', 'c.id')
            ->join('mina as m', 'lm.id_mina', '=', 'm.id')
            ->leftJoin('labor as l', 'lm.id_labor', '=', 'l.id')
            ->join('empleado as e', 'lm.id_empleado_registro', '=', 'e.id');

        if ($mes && $anio) {
            $query->whereYear('lm.created_at', $anio)
                  ->whereMonth('lm.created_at', $mes);
        }

        return $query->orderBy('lm.created_at', 'desc')->get()->toArray();
    }

    /**
     * Generar nuevo correlativo para lote_mineral.
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'lote_mineral',
            prefijo: 'SC',
            filtros: [],
            reseteo: Periodo::Anual,
            columnaFecha: 'created_at'
        );
    }

    /**
     * Crear un nuevo lote de mineral.
     */
    public static function registrar_lote(
        int $id_contratista,
        int $id_mina,
        ?int $id_labor,
        int $id_empleado_registro,
        ?string $codigo_interno,
        ?string $descripcion,
        string $correlativo,
        int $numero_correlativo,
        ?string $fecha_inicio_produccion = null,
        string $estado = 'Pendiente'
    ) {
        return LoteMineral::insertGetId([
            'id_contratista'    => $id_contratista,
            'id_mina'           => $id_mina,
            'id_labor'          => $id_labor,
            'id_empleado_registro' => $id_empleado_registro,
            'codigo_interno'    => $codigo_interno,
            'fecha_inicio_produccion' => $fecha_inicio_produccion,
            'descripcion'       => $descripcion,
            'correlativo'       => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'created_at'        => now(),
            'estado'            => $estado,
        ]);
    }

    /**
     * Obtener lote por ID.
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return DB::table('lote_mineral as lm')
            ->select([
                'lm.id as id_lote_mineral',
                'lm.correlativo',
                'lm.codigo_interno',
                'lm.fecha_inicio_produccion',
                'lm.descripcion',
                'lm.estado',
                'lm.created_at',
                'c.id as id_contratista',
                DB::raw("CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as contratista"),
                'm.id as id_mina',
                'm.nombre as mina',
                'l.id as id_labor',
                'l.nombre as labor',
                'l.prefijo as labor_prefijo',
                'e.id as id_empleado_registro',
                DB::raw("CONCAT(e.nombre, ' ', COALESCE(e.apellido, '')) as empleado_registro"),
            ])
            ->join('empleado as c', 'lm.id_contratista', '=', 'c.id')
            ->join('mina as m', 'lm.id_mina', '=', 'm.id')
            ->leftJoin('labor as l', 'lm.id_labor', '=', 'l.id')
            ->join('empleado as e', 'lm.id_empleado_registro', '=', 'e.id')
            ->where('lm.id', $id_lote)
            ->first();
    }
}
