<?php

namespace App\Modules\Empresa\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear un cargo.
 */
class StoreCargoRequest extends FormRequest
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
            'nombre' => 'required|string|max:64',
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
            'nombre.required' => 'El nombre del cargo es obligatorio',
            'nombre.max' => 'El nombre del cargo no puede exceder 64 caracteres',
        ];
    }
}
