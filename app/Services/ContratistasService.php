<?php

namespace App\Services;

use App\Data\ContratistasData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ContratistasService
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        $contratistas = ContratistasData::get_contratistas(id_mina: $id_mina, id_contratista: $id_contratista);

        return ApiResponse::success($contratistas);
    }

    /**
     * Registrar un nuevo contratista
     */
    public static function crear_contratista(
        int $id_mina,
        string $nombre,
        string $apellido,
        array $ids_labor = [],
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
        ?bool $return_object = false
    ) {
        if (ContratistasData::ya_existe(dni: $dni, ruc: $ruc, carnet_extranjeria: $carnet_extranjeria, pasaporte: $pasaporte)) {
            return ApiResponse::error('Ya existe un contratista registrado con estos documentos.');
        }

        $url_foto = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-contratistas', [$foto])[0];
            if (! empty($archivo)) {
                $url_foto = $archivo['url'];
            }
        }

        return DB::transaction(function () use ($id_mina, $nombre, $apellido, $dni, $ruc, $carnet_extranjeria, $pasaporte, $fecha_nacimiento, $genero, $direccion, $telefono, $email, $url_foto, $ids_labor, $return_object) {
            $id = ContratistasData::crear_contratista(
                id_mina: $id_mina,
                nombre: $nombre,
                apellido: $apellido,
                dni: $dni,
                ruc: $ruc,
                carnet_extranjeria: $carnet_extranjeria,
                pasaporte: $pasaporte,
                fecha_nacimiento: $fecha_nacimiento,
                url_foto: $url_foto,
                genero: $genero,
                direccion: $direccion,
                telefono: $telefono,
                email: $email
            );

            ContratistasData::asignar_labor($id, $ids_labor);

            if ($return_object) {
                $nuevoContratista = ContratistasData::get_contratistas(id_contratista: $id);

                return ApiResponse::success(
                    $nuevoContratista,
                    'Contratista registrado correctamente'
                );
            }

            return ApiResponse::success($id, 'Contratista registrado correctamente');
        });
    }
}
