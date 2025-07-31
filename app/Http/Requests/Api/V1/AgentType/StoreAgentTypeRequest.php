<?php

namespace App\Http\Requests\Api\V1\AgentType;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentTypeRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255|unique:agent_types,name',
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