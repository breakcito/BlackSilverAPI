<?php

namespace App\Modules\Contratistas\Service;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\Contratistas\Data\ContratistasData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ContratistasService
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(?int $id_mina = null)
    {
        $contratistas = ContratistasData::get_contratistas(id_mina: $id_mina);

        foreach ($contratistas as $contratista) {
            if ($contratista->path_foto && !str_starts_with($contratista->path_foto, 'http')) {
                $contratista->path_foto = asset('storage/' . $contratista->path_foto);
            }
        }

        return ApiResponse::success($contratistas);
    }

    /**
     * Registrar un nuevo contratista
     */
    public static function crear_contratista(
        string $nombre,
        string $apellido,
        ?int $id_mina = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?UploadedFile $foto = null,
        array $ids_labor = []
    ) {
        if ($dni && ContratistasData::existe_dni($dni)) {
            return ApiResponse::error('El DNI ingresado ya se encuentra registrado.');
        }

        $path_foto = null;
        if ($foto && $foto->isValid()) {
            $archivos = ArchivoHelper::guardarArchivos('fotos-contratistas', [$foto]);
            if (!empty($archivos)) {
                $path_foto = asset('storage/' . $archivos[0]['path_relativo']);
            }
        }

        return DB::transaction(function () use (
            $id_mina,
            $nombre,
            $apellido,
            $dni,
            $ruc,
            $carnet_extranjeria,
            $pasaporte,
            $fecha_nacimiento,
            $path_foto,
            $ids_labor
        ) {
            $id = ContratistasData::crear_contratista(
                $id_mina,
                $nombre,
                $apellido,
                $dni,
                $ruc,
                $carnet_extranjeria,
                $pasaporte,
                $fecha_nacimiento,
                $path_foto
            );

            if ($id_mina) {
                foreach ($ids_labor as $id_labor) {
                    if (ContratistasData::labor_pertenece_a_mina((int)$id_labor, $id_mina)) {
                        ContratistasData::asignar_labor($id, (int)$id_labor);
                    }
                }
            }

            $nuevoContratista = ContratistasData::get_contratista_by_id($id);

            return ApiResponse::success(
                $nuevoContratista,
                'Contratista registrado correctamente'
            );
        });
    }

    /**
     * Actualizar la foto de un contratista
     */
    public static function actualizar_foto(int $id_contratista, ?UploadedFile $file)
    {
        $archivos = ArchivoHelper::guardarArchivos('fotos-contratistas', [$file]);
        if (empty($archivos)) {
            return ApiResponse::error('No se pudo procesar la imagen.');
        }

        $path_foto = asset('storage/' . $archivos[0]['path_relativo']);
        ContratistasData::actualizar_foto($id_contratista, $path_foto);

        $contratista = ContratistasData::get_contratista_by_id($id_contratista);

        return ApiResponse::success($contratista, 'Foto de perfil actualizada correctamente');
    }

    /**
     * Obtener labores disponibles en una mina para un contratista
     */
    public static function get_labores_disponibles(int $id_mina, ?int $id_contratista = null)
    {
        $data = ContratistasData::get_labores_disponibles_mina($id_mina, $id_contratista);
        return ApiResponse::success($data);
    }

    /**
     * Obtener labores ya asignadas a un contratista
     */
    public static function get_labores_contratista(int $id_contratista)
    {
        $data = ContratistasData::get_labores_contratista($id_contratista);
        return ApiResponse::success($data);
    }

    /**
     * Asignar labores a un contratista
     */
    public static function asignar_labores(int $id_contratista, ?int $id_mina, array $ids_labor)
    {
        return DB::transaction(function () use ($id_contratista, $id_mina, $ids_labor) {
            // 1. Eliminar labores anteriores
            ContratistasData::eliminar_labores_contratista($id_contratista);

            // 2. Actualizar mina del contratista
            DB::table('contratista')->where('id', $id_contratista)->update(['id_mina' => $id_mina]);

            // 3. Asignar nuevas labores (si hay mina)
            if ($id_mina) {
                foreach ($ids_labor as $id_labor) {
                    if (ContratistasData::labor_pertenece_a_mina((int)$id_labor, $id_mina)) {
                        ContratistasData::asignar_labor($id_contratista, (int)$id_labor);
                    }
                }
            }

            $editado = ContratistasData::get_contratista_by_id($id_contratista);
            return ApiResponse::success($editado, 'Labores asignadas correctamente');
        });
    }
}
