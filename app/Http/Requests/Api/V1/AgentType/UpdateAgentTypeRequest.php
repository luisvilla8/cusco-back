<?php

namespace App\Http\Requests\Api\V1\AgentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Agregar lógica de autorización si es necesario
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $agentTypeId = $this->route('id'); // Obtener ID desde la ruta

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('agent_types')->ignore($agentTypeId)
            ],
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del tipo de agente es obligatorio.',
            'name.unique' => 'Ya existe un tipo de agente con este nombre.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 500 caracteres.',
        ];
    }
}