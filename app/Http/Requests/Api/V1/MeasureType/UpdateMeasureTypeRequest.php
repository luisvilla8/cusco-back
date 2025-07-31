<?php

namespace App\Http\Requests\Api\V1\MeasureType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeasureTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $measureTypeId = $this->route('id');

        return [
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('measure_types')->ignore($measureTypeId)
            ],
            'acronym' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('measure_types')->ignore($measureTypeId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La descripción de la unidad de medida es obligatoria.',
            'description.unique' => 'Ya existe una unidad de medida con esta descripción.',
            'description.max' => 'La descripción no puede tener más de 255 caracteres.',
            'acronym.required' => 'El acrónimo es obligatorio.',
            'acronym.unique' => 'Ya existe una unidad de medida con este acrónimo.',
            'acronym.max' => 'El acrónimo no puede tener más de 20 caracteres.',
        ];
    }
}