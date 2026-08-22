<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo',
        'id_contrato_vigente',
        'id_mina',
        'id_empresa',
        'qr_token',
        'nombre',
        'apellido',
        'dni',
        'genero',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'con_contrato',
        'direccion',
        'telefono',
        'email',
        'url_foto',
        'es_contratista',
        'estado',
    ];

    protected $casts = [
        'es_contratista' => 'boolean',
        'con_contrato' => 'boolean',
        'fecha_nacimiento' => 'date',
        'id_contrato_vigente' => 'integer',
        'id_cargo' => 'integer',
        'id_mina' => 'integer',
        'id_empresa' => 'integer',
    ];

    /**
     * Actualizar campos editables de un empleado (no contratista).
     *
     * NO modifica `id_contrato_vigente` ni `con_contrato` — esos campos
     * se gestionan en otra capa (módulo ContratosEmpleado + endpoint
     * toggle_con_contrato). Tampoco toca `qr_token`, `estado`, ni
     * `es_contratista`.
     *
     * El área del empleado se deriva del JOIN `cargo.id_area`, por lo
     * que NO se persiste como columna propia: basta con actualizar
     * `id_cargo` y el listado re-proyecta el área vía JOIN.
     *
     * Los parámetros `id_cargo` e `id_empresa` son "opcionales con
     * sentido": si vienen `null`, NO se incluyen en el UPDATE (se
     * preserva el valor actual). Esto permite al Service omitirlos
     * cuando el empleado tiene contrato vigente sin pisar la
     * referencia que mantiene el contrato.
     */
    public static function actualizar_empleado(
        int $id,
        string $nombre,
        string $apellido,
        ?string $genero = null,
        ?string $dni = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $id_cargo = null,
        ?int $id_empresa = null,
    ): bool {
        $data = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'genero' => $genero,
            'dni' => $dni,
            'fecha_nacimiento' => $fecha_nacimiento,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
        ];

        if ($id_cargo !== null) {
            $data['id_cargo'] = $id_cargo;
        }
        if ($id_empresa !== null) {
            $data['id_empresa'] = $id_empresa;
        }

        return self::where('id', $id)->update($data) >= 0;
    }

    /**
     * Actualizar campos editables de un contratista.
     *
     * NO modifica `id_mina` (se gestiona por AsignacionLaboresContratista),
     * ni `id_contrato_vigente`, `con_contrato`, `qr_token`, `estado`,
     * `es_contratista`.
     */
    public static function actualizar_contratista(
        int $id,
        string $nombre,
        string $apellido,
        ?string $genero = null,
        ?string $dni = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
    ): bool {
        return self::where('id', $id)->update([
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
