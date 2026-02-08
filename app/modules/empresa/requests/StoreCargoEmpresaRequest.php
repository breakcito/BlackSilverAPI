<?php

namespace App\Modules\Empresa\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear asociación cargo-empresa.
 */
class StoreCargoEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_area_empresa' => 'required|integer',
            'id_cargo' => 'required|integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_area_empresa.required' => 'El ID de área-empresa es obligatorio',
            'id_area_empresa.integer' => 'El ID de área-empresa debe ser un número entero',
            'id_cargo.required' => 'El ID del cargo es obligatorio',
            'id_cargo.integer' => 'El ID del cargo debe ser un número entero',
        ];
    }
}
