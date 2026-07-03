<?php

namespace App\Modules\Contratistas\Service;

use App\Data\ContratistasData as ContratistasDataGlobal;
use App\Modules\Contratistas\Data\ContratistasData;
use App\Services\ContratistasService as ContratistasServiceGlobal;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
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
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?UploadedFile $foto = null,
        array $ids_labor = []
    ) {
        $response = ContratistasServiceGlobal::crear_contratista(
            id_mina: $id_mina,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            genero: $genero,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            foto: $foto,
            ids_labor: $ids_labor,
            return_object: false
        );

        if ($response['success']) {
            $id = $response['data'];

            $contratista = ContratistasData::get_contratistas(id_contratista: $id);
            if ($contratista) {
                $contratista->labores_asignadas = $contratista->labores_asignadas ?? [];
            }

            return ApiResponse::success($contratista, 'Contratista registrado correctamente');
        }

        return ApiResponse::error($response['message']);
    }

    /**
     * Actualizar la foto del contratista
     */
    public static function actualizar_foto(int $id_contratista, ?UploadedFile $nueva_foto = null): array|object
    {
        $emp = ContratistasData::get_contratista_dinamico_by_id($id_contratista, ['url_foto']);
        $url_foto_old = ! empty($emp['url_foto']) ? $emp['url_foto'] : null;

        // Caso: eliminar foto (sin nueva)
        if (is_null($nueva_foto)) {
            if ($url_foto_old) {
                ArchivoHelper::eliminarArchivo($url_foto_old);
                ContratistasData::actualizar_foto(id_contratista: $id_contratista, url_foto: null);

                return ApiResponse::success(null, 'Foto eliminada correctamente.');
            }

            return ApiResponse::success(null, 'No hay foto para eliminar.');
        }

        // Caso: actualizar o agregar foto
        if ($url_foto_old) {
            $resultado = ArchivoHelper::reemplazarArchivo($url_foto_old, 'fotos-contratistas', $nueva_foto);
        } else {
            $resultado = ArchivoHelper::guardarArchivos('fotos-contratistas', [$nueva_foto]);
        }
        $foto = $resultado[0] ?? null;
        $url_foto = $foto['url'] ?? null;

        if (empty($url_foto)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        ContratistasData::actualizar_foto(id_contratista: $id_contratista, url_foto: $url_foto);

        return ApiResponse::success($url_foto, 'Foto actualizada correctamente.');
    }

    /**
     * Asignar labores a un contratista
     */
    public static function asignar_labores(int $id_contratista, ?int $id_mina, array $ids_labor)
    {
        return DB::transaction(function () use ($id_contratista, $id_mina, $ids_labor) {
            // 1. Eliminar labores anteriores
            ContratistasData::eliminar_labores_asignadas($id_contratista);

            // 2. Actualizar mina del contratista
            ContratistasData::update_mina($id_contratista, $id_mina);

            // 3. Asignar nuevas labores (si hay mina)
            ContratistasDataGlobal::asignar_labor(id_contratista: $id_contratista, id_labores: $ids_labor);

            $editado = ContratistasData::get_contratistas(id_contratista: $id_contratista);

            return ApiResponse::success($editado, 'Labores asignadas correctamente');
        });
    }
}
