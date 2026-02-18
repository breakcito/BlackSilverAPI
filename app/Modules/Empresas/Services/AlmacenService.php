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
    public function get_almacenes(?int $id_empresa = null)
    {
        $almacenes = Almacen::get_almacenes($id_empresa);
        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un nuevo almacén CON lógica de correlativos automáticos.
     */
    public function crear_almacen(int $id_empresa, string $nombre, ?string $descripcion, bool $es_principal)
    {
        // 1. Verificar nombre duplicado en la misma empresa
        if (Almacen::verificar_nombre_existente($id_empresa, $nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre en la empresa.');
        }

        // 2. Generar Correlativo Automático
        $ultimo_numero = Almacen::get_ultimo_correlativo($id_empresa);
        $nuevo_numero = $ultimo_numero + 1;
        $correlativo = 'ALM'; // Fijo por ahora, o podría venir de configuración

        $id = Almacen::crear_almacen($id_empresa, $correlativo, $nuevo_numero, $nombre, $descripcion, $es_principal);

        return ApiResponse::success(Almacen::get_almacen_by_id($id), 'Almacén creado correctamente');
    }

    /**
     * Asignar responsable (Lógica de reemplazo automático).
     */
    public function asignar_responsable(int $id_almacen, int $id_usuario_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        // 1. Cerrar responsable activo actual (si existe)
        // OJO: Asumimos que la nueva fecha inicio es la fecha de cierre del anterior.
        // O simplemente cerramos con la misma fecha de hoy si no se especifica.
        // Por simplicidad, cerraremos con fecha AHORA o la fecha inicio del nuevo.
        
        // Simulación de transacción para evitar inconsistencias
        DB::beginTransaction();
        try {
            // Cerrar anteriores activos
            Almacen::cerrar_responsable_activo($id_almacen, $fecha_inicio); // Usamos fecha inicio del nuevo como fin del anterior

            // Crear nuevo
            $id = Almacen::asignar_responsable($id_almacen, $id_usuario_empresa, $fecha_inicio, $fecha_fin);
            
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
    public function get_responsables_historial(int $id_almacen)
    {
        $historial = Almacen::get_responsables_historial($id_almacen);
        return ApiResponse::success($historial);
    }
    
    /**
     * Asignar labor a almacén.
     */
    public function asignar_labor(int $id_almacen, int $id_labor)
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
    public function get_labores_asignadas(int $id_almacen)
    {
        $labores = Almacen::get_labores_asignadas($id_almacen);
        return ApiResponse::success($labores);
    }
}
