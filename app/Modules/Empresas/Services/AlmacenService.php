<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Almacen;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class AlmacenService
{
    /**
     * Listar almacenes de una empresa.
     */
    /**
     * Listar todos los almacenes.
     */
    public function get_almacenes()
    {
        $almacenes = Almacen::get_almacenes();
        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén.
     */
    public function crear_almacen(string $nombre, ?string $descripcion, bool $es_principal)
    {
        if (Almacen::verificar_nombre_existente($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id = Almacen::crear_almacen($nombre, $descripcion, $es_principal);

        return ApiResponse::success(Almacen::get_almacen_by_id($id), 'Almacén creado correctamente');
    }

    /**
     * Asignar responsable (Lógica de reemplazo automático).
     */
    public function asignar_responsable_almacen(int $id_almacen, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        // Simulación de transacción para evitar inconsistencias
        DB::beginTransaction();
        try {
            // Cerrar anteriores activos
            Almacen::cerrar_responsable_activo($id_almacen, $fecha_inicio); 

            // Crear nuevo. Nota: id_usuario es de tabla usuario.
            $id = Almacen::asignar_responsable($id_almacen, $id_usuario, $fecha_inicio, $fecha_fin);
            
            DB::commit();
            return ApiResponse::success(['id_asignacion' => $id], 'Responsable asignado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al asignar responsable: ' . $e->getMessage());
        }
    }

    /**
     * Obtener historial de responsables.
     */
    public function get_responsables_almacen(int $id_almacen)
    {
        $historial = Almacen::get_responsables_historial($id_almacen);
        return ApiResponse::success($historial);
    }
    
    /**
     * Asignar labor a almacén.
     */
    public function asignar_labor_almacen(int $id_almacen, int $id_labor)
    {
        if (Almacen::verificar_labor_asignada($id_almacen, $id_labor)) {
             return ApiResponse::error('Esta labor ya está asignada al almacén.');
        }
        
        $id = Almacen::asignar_labor($id_almacen, $id_labor);
        return ApiResponse::success(['id_asignacion' => $id], 'Labor asignada correctamente');
    }
    
     /**
     * Listar labores asignadas.
     */
    public function get_labores_almacen(int $id_almacen)
    {
        $labores = Almacen::get_labores_asignadas($id_almacen);
        return ApiResponse::success($labores);
    }
}
