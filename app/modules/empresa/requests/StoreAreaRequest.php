<?php

namespace App\Modules\Empresa\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para crear un área.
 */
class StoreAreaRequest extends FormRequest
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
            'nombre' => 'required|string|max:64',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del área es obligatorio',
            'nombre.max' => 'El nombre del área no puede exceder 64 caracteres',
        ];
    }
}
