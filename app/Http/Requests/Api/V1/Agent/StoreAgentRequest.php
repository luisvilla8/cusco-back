<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Agent\StoreAgentRequest.php

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'email' => 'nullable|email|max:255|unique:agents,email',
            'dni' => 'nullable|string|size:8|regex:/^\d{8}$/|unique:agents,dni',
            'ruc' => 'nullable|string|size:11|regex:/^\d{11}$/|unique:agents,ruc',
            'agent_type_id' => 'required|integer|exists:agent_types,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del agente es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'phone.max' => 'El teléfono no puede tener más de 20 caracteres.',
            'address.max' => 'La dirección no puede tener más de 500 caracteres.',
            'email.email' => 'El formato del email no es válido.',
            'email.unique' => 'Ya existe un agente con este email.',
            'email.max' => 'El email no puede tener más de 255 caracteres.',
            'dni.size' => 'El DNI debe tener exactamente 8 dígitos.',
            'dni.regex' => 'El DNI solo debe contener números.',
            'dni.unique' => 'Ya existe un agente con este DNI.',
            'ruc.size' => 'El RUC debe tener exactamente 11 dígitos.',
            'ruc.regex' => 'El RUC solo debe contener números.',
            'ruc.unique' => 'Ya existe un agente con este RUC.',
            'agent_type_id.required' => 'El tipo de agente es obligatorio.',
            'agent_type_id.exists' => 'El tipo de agente seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation()
    {
        // Limpiar DNI y RUC (remover espacios y caracteres especiales)
        if ($this->has('dni')) {
            $this->merge(['dni' => preg_replace('/\D/', '', $this->dni)]);
        }
        
        if ($this->has('ruc')) {
            $this->merge(['ruc' => preg_replace('/\D/', '', $this->ruc)]);
        }
    }
}