<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Trip\UpdateTripRequest.php

namespace App\Http\Requests\Api\V1\Trip;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $trip = Trip::find($this->route('id'));
        
        if (!$user || !$trip) {
            return false;
        }
        
        return $trip->canUserEdit($user);
    }

    public function rules(): array
    {
        return [
            'agent_id' => 'sometimes|required|exists:agents,id',
            'zone_id' => 'sometimes|required|exists:zones,id',
            'user_id' => 'sometimes|required|exists:users,id', //  CAMBIAR A REQUIRED
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'travel_expenses' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'total' => 'sometimes|nullable|numeric|min:0|max:999999.99', //  AGREGAR TOTAL
            'date_start' => 'sometimes|required|date',
            'date_end' => 'sometimes|required|date|after_or_equal:date_start',
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.exists' => 'El agente seleccionado no es válido',
            'zone_id.exists' => 'La zona seleccionada no es válida',
            'user_id.required' => 'El usuario es obligatorio', //  NUEVO
            'user_id.exists' => 'El usuario seleccionado no es válido',
            'name.required' => 'El nombre del viaje es obligatorio',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'description.max' => 'La descripción no puede exceder 1000 caracteres',
            'travel_expenses.numeric' => 'Los gastos de viaje deben ser un número',
            'travel_expenses.min' => 'Los gastos de viaje no pueden ser negativos',
            'travel_expenses.max' => 'Los gastos de viaje no pueden exceder 999,999.99',
            'total.numeric' => 'El total debe ser un número', //  NUEVO
            'total.min' => 'El total no puede ser negativo', //  NUEVO
            'total.max' => 'El total no puede exceder 999,999.99', //  NUEVO
            'date_start.date' => 'La fecha de inicio debe ser una fecha válida',
            'date_end.date' => 'La fecha de fin debe ser una fecha válida',
            'date_end.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio',
        ];
    }

    /**
     *  VALIDACIONES ADICIONALES - SIGUIENDO PATRÓN DE USER
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = auth()->user();
            $trip = Trip::find($this->route('id'));
            
            if (!$trip || !$user) {
                return;
            }
            
            $userRole = $user->role;
            $isAdmin = $userRole && in_array($userRole->name, ['Administrador', 'Super Admin']);
            
            // Verificar acceso a zona si se cambia
            if ($this->zone_id && !$isAdmin) {
                if (!$user->hasZone($this->zone_id)) {
                    $validator->errors()->add('zone_id', 'No tienes acceso a la zona seleccionada');
                }
            }
            
            // Solo admins pueden cambiar el user_id
            if ($this->has('user_id') && !$isAdmin) {
                $validator->errors()->add('user_id', 'No tienes permisos para cambiar el usuario asignado');
            }
        });
    }

    /**
     *  MANEJO DE ERRORES como en User
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}