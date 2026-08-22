<?php

namespace App\Data;

use App\Models\Empleado;
use App\Models\LaborContratista;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContratistasData
{
    /**
     * Crear un nuevo contratista
     */
    public static function crear_contratista(
        int $id_mina,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $url_foto = null,
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?string $qr_token = null,
    ) {
        $qr_token = !empty($qr_token) ? $qr_token : (string) Str::uuid();

        return Empleado::insertGetId([
            'id_mina' => $id_mina,
            'id_cargo' => null,
            'es_contratista' => 1,
            'qr_token' => $qr_token,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'genero' => $genero,
            'dni' => $dni,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
            'url_foto' => $url_foto,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function ya_existe(
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null
    ): bool {
        $dni = trim($dni ?? '');
        $ruc = trim($ruc ?? '');
        $carnet_extranjeria = trim($carnet_extranjeria ?? '');
        $pasaporte = trim($pasaporte ?? '');

        if ($dni === '' && $ruc === '' && $carnet_extranjeria === '' && $pasaporte === '') {
            return false;
        }

        return Empleado::query()
            ->where('es_contratista', 1)
            ->where(function ($q) use ($dni, $ruc, $carnet_extranjeria, $pasaporte) {
                $q->when($dni !== '', fn($q) => $q->orWhere('dni', $dni))
                    ->when($ruc !== '', fn($q) => $q->orWhere('ruc', $ruc))
                    ->when($carnet_extranjeria !== '', fn($q) => $q->orWhere('carnet_extranjeria', $carnet_extranjeria))
                    ->when($pasaporte !== '', fn($q) => $q->orWhere('pasaporte', $pasaporte));
            })
            ->exists();
    }

    /**
     * Asignar una o varias labores a un contratista
     * @param array $ids_labor Lista de id_labor a asignar como activas desde hoy
     */
    public static function asignar_labor(int $id_contratista, array $ids_labor): void
    {
        if (empty($ids_labor)) {
            return;
        }

        $ids_labor = array_values(array_unique(array_map('intval', $ids_labor)));

        $data = array_map(
            fn(int $id_labor) => [
                'id_contratista' => $id_contratista,
                'id_labor' => $id_labor,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => null,
                'estado' => 'Activo',
            ],
            $ids_labor
        );

        LaborContratista::insert($data);
    }

    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        $sql = '
        SELECT
            c.id AS id_contratista,

            c.id_mina,
            mn.nombre AS mina,

            c.qr_token,
            CONCAT(c.nombre, " ", c.apellido) as nombre_completo,
            c.nombre,
            c.apellido,
            c.genero,
            c.dni,
            c.ruc,
            c.carnet_extranjeria,
            c.pasaporte,
            c.direccion,
            c.telefono,
            c.email,
            c.fecha_nacimiento,
            c.url_foto,

            c.con_contrato,
            c.id_contrato_vigente,
            ct.fecha_fin AS contrato_fecha_fin,
            ct.por_tiempo_indefinido AS contrato_por_tiempo_indefinido,

            GROUP_CONCAT(
                DISTINCT lc.id_labor
                ORDER BY lc.id_labor ASC
                SEPARATOR ","
            ) AS ids_labores_activas

        FROM empleado c
        LEFT JOIN mina mn ON mn.id = c.id_mina
        LEFT JOIN contrato_trabajo ct ON ct.id = c.id_contrato_vigente
        LEFT JOIN labor_contratista lc ON lc.id_contratista = c.id
            AND lc.estado = "Activo"
            AND lc.fecha_fin IS NULL
        WHERE c.es_contratista = 1
        ';

        $params = [];

        if ($id_contratista) {
            $sql .= ' AND c.id = :id_contratista';
            $params['id_contratista'] = $id_contratista;

            $sql .= ' GROUP BY c.id';
            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND c.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' GROUP BY c.id ORDER BY nombre_completo ASC';

        return DB::select($sql, $params);
    }

    /**
     * Actualizar datos personales + contacto de un contratista.
     * La foto NO se persiste aquí — va por el endpoint dedicado
     * `/contratistas/{id}/foto`. Tampoco se tocan `id_mina`,
     * `id_contrato_vigente`, `con_contrato`, `qr_token`, `estado`.
     */
    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $genero = null,
        ?string $dni = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
    ): bool {
        return Empleado::where('id', $id_contratista)->update([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'genero' => $genero,
            'dni' => $dni,
            'fecha_nacimiento' => $fecha_nacimiento,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
        ]) >= 0;
    }
}
