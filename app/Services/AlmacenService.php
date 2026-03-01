<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\AlmacenMina;
use App\Models\ResponsableAlmacen;
use App\Models\Usuario;
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
        if (Almacen::where('nombre', $nombre)->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)->exists()) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $almacen = Almacen::create([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_principal' => $es_principal,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        return ApiResponse::success($almacen, 'Almacén creado correctamente');
    }

    /**
     * Asignar responsable (Lógica de reemplazo automático).
     */
    public function asignar_responsable_almacen(int $id_almacen, int $id_empleado_front, string $fecha_inicio, ?string $fecha_fin)
    {
        // El Frontend envía el id_empleado genérico, buscamos su usuario real de sistema.
        $usuarioReal = Usuario::where('id_empleado', $id_empleado_front)->first();
        if (! $usuarioReal) {
            return ApiResponse::error('El empleado seleccionado no tiene cuenta de usuario en el sistema.');
        }
        $id_usuario_real = $usuarioReal->id;

        // Simulación de transacción para evitar inconsistencias
        DB::beginTransaction();
        try {
            // Cerrar anteriores activos
            ResponsableAlmacen::cerrar_responsable_activo($id_almacen, $fecha_inicio);

            // Crear nuevo usando el id de la tabla usuario
            $id = ResponsableAlmacen::asignar_responsable($id_almacen, $id_usuario_real, $fecha_inicio, $fecha_fin);

            DB::commit();

            return ApiResponse::success(['id_asignacion' => $id], 'Responsable asignado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error('Error al asignar responsable: '.$e->getMessage());
        }
    }

    /**
     * Obtener historial de responsables.
     */
    public function get_responsables_almacen(int $id_almacen)
    {
        $historial = ResponsableAlmacen::get_responsables_historial($id_almacen);

        return ApiResponse::success($historial);
    }

    /**
     * Asignar mina a almacén.
     */
    public function asignar_mina_almacen(int $id_almacen, int $id_mina)
    {
        if (AlmacenMina::verificar_mina_asignada($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está asignada al almacén.');
        }

        $id = AlmacenMina::asignar_mina($id_almacen, $id_mina);

        return ApiResponse::success(['id_asignacion' => $id], 'Mina asignada correctamente');
    }

    /**
     * Listar minas asignadas.
     */
    public function get_minas_almacen(int $id_almacen)
    {
        $minas = AlmacenMina::get_minas_asignadas($id_almacen);

        return ApiResponse::success($minas);
    }

    /**
     * Desasignar mina de almacén.
     */
    public function desasignar_mina_almacen(int $id_asignacion)
    {
        AlmacenMina::desasignar_mina($id_asignacion);

        return ApiResponse::success(null, 'Mina desvinculada del almacén correctamente');
    }
}
