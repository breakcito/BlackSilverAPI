<?php

namespace App\Data;

use App\Models\Empleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmpleadosData
{
    /**
     * Obtener listado simple de empleados
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?int $id_mina_excluyente = null,
        ?bool $con_cuenta = null,
        ?bool $solo_con_contrato_vigente = null,
        ?string $fecha_fin_programacion = null
    ) {
        $query = DB::table('empleado as emp')
            ->selectRaw('
            emp.id as id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
            emp.dni,
            emp.ruc,
            emp.url_foto,
            emp.qr_token,
            emp.genero,
            emp.con_contrato,
            emp.direccion,
            emp.telefono,
            emp.email,
            emp.id_contrato_vigente,
            emp.id_cargo,
            ct.estado AS contrato_estado,
            ct.por_tiempo_indefinido AS contrato_indefinido,
            ct.fecha_fin AS contrato_fecha_fin
            ')
            ->leftJoin('contrato_trabajo as ct', 'ct.id', '=', 'emp.id_contrato_vigente')
            ->where('emp.es_contratista', 0)
            ->where('emp.estado', $estado->value);

        // filtro por id
        if ($id_empleado !== null) {
            $query->where('emp.id', $id_empleado);

            return $query->first() ?? [];
        }

        // filtro por empleados ya asignados a un almacén
        if ($id_almacen_excluyente !== null) {
            $query->whereNotExists(function ($subquery) use ($id_almacen_excluyente) {
                $subquery->select(DB::raw(1))
                    ->from('responsable_almacen as res')
                    ->whereColumn('res.id_empleado', 'emp.id')
                    ->where('res.id_almacen', $id_almacen_excluyente)
                    ->where('res.estado', EstadoBase::Activo->value);
            });
        }

        // filtro por empleados ya asignados a una mina
        if ($id_mina_excluyente !== null) {
            $query->whereNotExists(function ($subquery) use ($id_mina_excluyente) {
                $subquery->select(DB::raw(1))
                    ->from('responsable_mina as res')
                    ->whereColumn('res.id_empleado', 'emp.id')
                    ->where('res.id_mina', $id_mina_excluyente)
                    ->where('res.estado', EstadoBase::Activo->value);
            });
        }

        // filtro listar solo empleados con/sin cuenta
        if ($con_cuenta !== null) {
            if ($con_cuenta == false) {
                $query->whereNotExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('usuario as u')
                        ->whereColumn('u.id_empleado', 'emp.id');
                });
            } else {
                $query->whereExists(function ($subquery) {
                    $subquery->select(DB::raw(1))
                        ->from('usuario as u')
                        ->whereColumn('u.id_empleado', 'emp.id');
                });
            }
        }

        // filtro listar solo empleados con contrato vigente Activo.
        // Se exige: con_contrato = 1, id_contrato_vigente NOT NULL, contrato.estado = 'Activo'.
        if ($solo_con_contrato_vigente === true) {
            $query->where('emp.con_contrato', 1)
                ->whereNotNull('emp.id_contrato_vigente')
                ->where('ct.estado', EstadoBase::Activo->value);
        }

        $rows = $query
            ->orderByRaw('CONCAT(emp.nombre, " ", emp.apellido) ASC')
            ->get();

        return $rows
            ->map(function ($row) use ($fecha_fin_programacion) {
                $row = (array) $row;
                // Cast manual: la query builder no aplica los $casts del modelo.
                $row['con_contrato'] = (bool) ($row['con_contrato'] ?? 0);
                $row['contrato_indefinido'] = (bool) ($row['contrato_indefinido'] ?? 0);

                if ($fecha_fin_programacion !== null && $fecha_fin_programacion !== '') {
                    $contrato_indefinido = $row['contrato_indefinido'];
                    $contrato_fecha_fin = $row['contrato_fecha_fin'] ?? null;
                    $row['puede_cubrir'] = $contrato_indefinido
                        || $contrato_fecha_fin === null
                        || (string) $contrato_fecha_fin >= (string) $fecha_fin_programacion;
                } else {
                    $row['puede_cubrir'] = true;
                }

                return $row;
            })
            ->toArray();
    }

    /**
     * Verificar si ya existe un empleado con el mismo documento
     */
    public static function ya_existe(
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null
    ): bool {
        return Empleado::query()
            ->where('es_contratista', 0)
            ->where(function ($q) use ($dni, $ruc, $carnet_extranjeria, $pasaporte) {
                $q->when($dni !== null, fn ($q) => $q->orWhere('dni', $dni))
                    ->when($ruc !== null, fn ($q) => $q->orWhere('ruc', $ruc))
                    ->when(
                        $carnet_extranjeria !== null,
                        fn ($q) => $q->orWhere('carnet_extranjeria', $carnet_extranjeria)
                    )
                    ->when($pasaporte !== null, fn ($q) => $q->orWhere('pasaporte', $pasaporte));
            })
            ->exists();
    }

    /**
     * Crear un nuevo empleado
     */
    public static function crear_empleado(
        int $id_cargo,
        string $nombre,
        string $apellido,
        bool $con_contrato = false,
        ?int $id_contrato_vigente = null,
        ?string $genero = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?string $url_foto = null,
        ?string $qr_token = null,
    ) {
        $qr_token = ! empty($qr_token) ? $qr_token : (string) Str::uuid();

        return Empleado::insertGetId([
            'id_cargo' => $id_cargo,
            'id_contrato_vigente' => $id_contrato_vigente,
            'qr_token' => $qr_token,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'genero' => $genero,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'con_contrato' => $con_contrato,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
            'url_foto' => $url_foto,
            'es_contratista' => 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Metodo para consultar datos dinamicos de uno o varios empleados a la vez
     */
    public static function get_empleado_dinamico_by_id(int|array $id_empleado, array $columnas): ?array
    {
        $esArray = is_array($id_empleado);
        $ids = $esArray ? $id_empleado : [$id_empleado];
        // Forzamos la inclusión del ID con su alias
        if (! in_array('id as id_empleado', $columnas)) {
            $columnas[] = 'id as id_empleado';
        }
        $query = Empleado::where('es_contratista', 0)->whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }

        return $query->first()?->toArray();
    }

    /**
     * Actualizar foto de un empleado
     */
    public static function actualizar_foto(
        int $id_empleado,
        ?string $url_foto = null
    ) {
        return Empleado::where('id', $id_empleado)->update([
            'url_foto' => $url_foto,
        ]);
    }
}
