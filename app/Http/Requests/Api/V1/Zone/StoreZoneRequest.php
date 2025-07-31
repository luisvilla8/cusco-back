<?php

namespace App\Http\Requests\Api\V1\Zone;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:zones,name',
            'description' => 'nullable|string|max:500',
            'location_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la zona es obligatorio.',
            'name.unique' => 'Ya existe una zona con este nombre.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 500 caracteres.',
            'location_url.url' => 'La URL de ubicación debe tener un formato válido.',
            'location_url.max' => 'La URL de ubicación no puede tener más de 500 caracteres.',
        ];
    }
}