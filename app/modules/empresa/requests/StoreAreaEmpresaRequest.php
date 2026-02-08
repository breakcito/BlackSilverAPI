<?php

namespace App\Modules\Empresa\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear asociación área-empresa.
 */
class StoreAreaEmpresaRequest extends FormRequest
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
            'id_area' => 'required|integer',
            'id_empresa' => 'required|integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_area.required' => 'El ID del área es obligatorio',
            'id_area.integer' => 'El ID del área debe ser un número entero',
            'id_empresa.required' => 'El ID de la empresa es obligatorio',
            'id_empresa.integer' => 'El ID de la empresa debe ser un número entero',
        ];
    }
}
