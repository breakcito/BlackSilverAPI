<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Concesion;
use App\Modules\Empresas\Models\Labor;
use App\Modules\Empresas\Models\Mina;
use App\Modules\Empresas\Models\TipoLabor;
use App\Shared\Enums\EstadoBase;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;

class LaborService
{
    public function get_labores(?int $id_mina = null)
    {
        $labores = Labor::get_labores($id_mina);
        return ApiResponse::success($labores);
    }

    public function get_labor_by_id(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }
        return ApiResponse::success($labor);
    }

    public function crear_labor(
        int $id_empresa,
        int $id_mina,
        int $id_tipo_labor,
        string $nombre,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null
    ) {
        if (Labor::where('id_mina', $id_mina)->where('nombre', $nombre)->exists()) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        // Validamos si la concesion de la mina tiene un contrato vigente con la empresa
        $id_concesion = Mina::where('id', $id_mina)->value('id_concesion');
        if (!$id_concesion) {
            return ApiResponse::error('Mina no encontrada');
        }
        if (!Concesion::check_contrato_vigente($id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa seleccionada no tiene un contrato vigente en la concesión de esta mina.');
        }

        // obtener prefijo del tipo de labor para generar correlativo
        $prefijo = TipoLabor::where('id', $id_tipo_labor)->value('prefijo');
        if (!$prefijo) {
            return ApiResponse::error('Tipo de labor no encontrado');
        }
        $correlativo_helper = CorrelativoHelper::generar('labor', $prefijo, ['id_empresa' => $id_empresa]);

        $new_labor = Labor::create([
            'id_empresa'         => $id_empresa,
            'id_mina'            => $id_mina,
            'id_tipo_labor'      => $id_tipo_labor,
            'correlativo'        => $correlativo_helper['correlativo'],
            'numero_correlativo' => $correlativo_helper['numero_correlativo'],
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta'               => $veta,
            'ancho'              => $ancho,
            'alto'               => $alto,
            'nivel'              => $nivel,
            'created_at'         => now(),
            'estado'             => EstadoBase::Activo->value
        ]);
        return ApiResponse::success($new_labor);
    }

    public function update_labor(
        int $id,
        int $id_empresa,
        int $id_mina,
        int $id_tipo_labor,
        string $nombre,
        ?string $descripcion,
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null,
        ?string $fecha_fin = null,
        ?string $estado = null
    ) {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        if (Labor::verificar_labor_existente($id_mina, $nombre, $id)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta mina');
        }

        $mina = Mina::get_mina_by_id($id_mina);
        if (!$mina) {
            return ApiResponse::error('Mina no encontrada');
        }

        if (!Mina::check_contrato_vigente($mina->id_concesion, $id_empresa)) {
            return ApiResponse::error('La empresa seleccionada no tiene un contrato vigente en la concesión de esta mina.');
        }

        Labor::update_labor(
            $id,
            $id_empresa,
            $id_mina,
            $id_tipo_labor,
            $labor->codigo_correlativo, // Se mantiene el que tenía originamente
            $nombre,
            $descripcion,
            $tipo_sostenimiento,
            $veta,
            $ancho,
            $alto,
            $nivel,
            $fecha_fin,
            $estado
        );

        return ApiResponse::success(['mensaje' => 'Labor actualizada correctamente']);
    }

    public function delete_labor(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::delete_labor($id);
        return ApiResponse::success(['mensaje' => 'Labor inactivada correctamente (finalizada)']);
    }
}
