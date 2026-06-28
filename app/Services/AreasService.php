<?php
namespace App\Services;

use App\Data\AreasData;
use App\Data\CargosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class AreasService
{
    /**
     * Listar áreas.
     */
    public static function get_areas(
        ?int $id_area = null,
        ?EstadoBase $estado = null,
        bool $con_cargos = false,
    ) {
        $data = AreasData::get_areas(
            id_area: $id_area,
            estado: $estado
        );

        if ($con_cargos) {
            if ($id_area !== null) {
                if ($data) {
                    $data->cargos = CargosData::get_cargos(id_area: (int) $data->id_area, estado: $estado);
                }
            } else if (is_array($data) && !empty($data)) {
                $ids = array_map(function ($area) {
                    return (int) $area->id_area;
                }, $data);

                $allCargos = CargosData::get_cargos(id_area: $ids, estado: $estado);

                $cargosByArea = [];
                foreach ($allCargos as $cargo) {
                    $cargosByArea[(int) $cargo->id_area][] = $cargo;
                }

                foreach ($data as $area) {
                    $area->cargos = $cargosByArea[(int) $area->id_area] ?? [];
                }
            }
        }

        return ApiResponse::success($data);
    }

    /**
     * Crear área y sus cargos iniciales dentro de una transacción.
     *
     * @param array|null $cargos Array de strings con los nombres de los cargos a crear: ['nombre' => string][]
     */
    public static function crear_area(string $nombre, ?array $cargos = null): array|object
    {
        if (AreasData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un área con este nombre.');
        }

        $nueva = DB::transaction(function () use ($nombre, $cargos) {
            $id = AreasData::crear_area($nombre);

            if (!empty($cargos)) {
                foreach ($cargos as $cargo) {
                    CargosService::crear_cargo(nombre: $cargo['nombre'], id_area: $id);
                }
            }

            $area = AreasData::get_areas(id_area: $id);
            if ($area) {
                $area->cargos = CargosData::get_cargos(id_area: $id);
            }
            return $area;
        });

        return ApiResponse::success($nueva, 'Área creada correctamente');
    }
}
