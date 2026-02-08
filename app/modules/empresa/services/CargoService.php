<?php

namespace App\Modules\Empresa\Services;

use App\Modules\Empresa\Infraestructure\Models\Area;
use App\Modules\Empresa\Infraestructure\Models\AreaEmpresa;
use App\Modules\Empresa\Infraestructure\Models\Cargo;
use App\Modules\Empresa\Infraestructure\Models\CargoEmpresa;
use App\Modules\Empresa\Infraestructure\Models\Empresa;
use App\Shared\Responses\ApiResponse;

/**
 * Servicio para gestión de cargos, áreas y sus asociaciones con empresas.
 */
class CargoService
{
    /**
     * Registrar una nueva área.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function registrarArea(string $nombre): array
    {
        try {
            $area = Area::crearArea($nombre);

            if (! $area) {
                return ApiResponse::array(false, null, 'Error al crear el área');
            }

            return ApiResponse::array(true, $area, 'Área creada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Registrar un nuevo cargo.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function registrarCargo(int $idArea, string $nombre): array
    {
        try {
            $area = Area::buscarPorId($idArea);

            if (! $area) {
                return ApiResponse::array(false, null, 'El área especificada no existe');
            }

            $cargo = Cargo::crearCargo($idArea, $nombre);

            if (! $cargo) {
                return ApiResponse::array(false, null, 'Error al crear el cargo');
            }

            return ApiResponse::array(true, $cargo, 'Cargo creado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Registrar una asociación área-empresa.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function registrarAreaEmpresa(int $idArea, int $idEmpresa): array
    {
        try {
            $area = Area::buscarPorId($idArea);

            if (! $area) {
                return ApiResponse::array(false, null, 'El área especificada no existe');
            }

            $empresa = Empresa::buscarPorId($idEmpresa);

            if (! $empresa) {
                return ApiResponse::array(false, null, 'La empresa especificada no existe');
            }

            $existente = AreaEmpresa::buscarPorAreaYEmpresa($idArea, $idEmpresa);

            if ($existente) {
                return ApiResponse::array(false, null, 'Esta área ya está asociada a la empresa');
            }

            $areaEmpresa = AreaEmpresa::crearAreaEmpresa($idArea, $idEmpresa);

            if (! $areaEmpresa) {
                return ApiResponse::array(false, null, 'Error al asociar el área con la empresa');
            }

            return ApiResponse::array(true, $areaEmpresa, 'Área asociada a empresa exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }

    /**
     * Registrar una asociación cargo-empresa.
     *
     * @return array{success: bool, data: mixed, message: string|null}
     */
    public function registrarCargoEmpresa(int $idAreaEmpresa, int $idCargo): array
    {
        try {
            $areaEmpresa = AreaEmpresa::buscarPorId($idAreaEmpresa);

            if (! $areaEmpresa) {
                return ApiResponse::array(false, null, 'La relación área-empresa no existe');
            }

            $cargo = Cargo::buscarPorId($idCargo);

            if (! $cargo) {
                return ApiResponse::array(false, null, 'El cargo especificado no existe');
            }

            $cargoEmpresa = CargoEmpresa::crearCargoEmpresa($idAreaEmpresa, $idCargo);

            if (! $cargoEmpresa) {
                return ApiResponse::array(false, null, 'Error al asociar el cargo con la empresa');
            }

            return ApiResponse::array(true, $cargoEmpresa, 'Cargo asociado a empresa exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::array(false, null, 'Error: '.$e->getMessage());
        }
    }
}
