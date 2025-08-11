<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Trip\StoreTripRequest.php

namespace App\Http\Requests\Api\V1\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'agent_id' => 'required|integer|exists:agents,id',
            'zone_id' => 'required|integer|exists:zones,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'travel_expenses' => 'required|numeric|min:0',
            'date_start' => 'required|date|after_or_equal:today',
            'date_end' => 'required|date|after:date_start',
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.required' => 'El agente es obligatorio.',
            'agent_id.exists' => 'El agente seleccionado no existe.',
            'zone_id.required' => 'La zona es obligatoria.',
            'zone_id.exists' => 'La zona seleccionada no existe.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'name.required' => 'El nombre del viaje es obligatorio.',
            'travel_expenses.required' => 'Los gastos de viaje son obligatorios.',
            'travel_expenses.min' => 'Los gastos de viaje deben ser mayores o iguales a 0.',
            'date_start.required' => 'La fecha de inicio es obligatoria.',
            'date_start.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'date_end.required' => 'La fecha de fin es obligatoria.',
            'date_end.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }

    //  MANTENER SOLO VALIDACIONES DE NEGOCIO
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            
            if ($user->hasRole('Vendedor')) {
                $zoneId = $this->input('zone_id');
                
                if ($zoneId && !$user->hasZone($zoneId)) {
                    $zone = \App\Models\Zone::find($zoneId);
                    $zoneName = $zone ? $zone->name : "ID {$zoneId}";
                    $validator->errors()->add('zone_id', "No tienes permisos para crear viajes en la zona: {$zoneName}");
                }
                
                $assignedUserId = $this->input('user_id');
                if ($assignedUserId && $assignedUserId !== $user->id) {
                    $validator->errors()->add('user_id', 'Los vendedores solo pueden crear viajes para sí mismos.');
                }
            }
            
            $assignedUserId = $this->input('user_id');
            $zoneId = $this->input('zone_id');
            
            if ($assignedUserId && $zoneId && $assignedUserId !== $user->id) {
                $assignedUser = \App\Models\User::find($assignedUserId);
                
                if ($assignedUser && !$assignedUser->hasZone($zoneId)) {
                    $zone = \App\Models\Zone::find($zoneId);
                    $zoneName = $zone ? $zone->name : "ID {$zoneId}";
                    $validator->errors()->add('user_id', "El usuario asignado no tiene permisos para la zona: {$zoneName}");
                }
            }
            
            $dateStart = $this->input('date_start');
            $dateEnd = $this->input('date_end');
            
            if ($dateStart && $dateEnd) {
                $start = \Carbon\Carbon::parse($dateStart);
                $end = \Carbon\Carbon::parse($dateEnd);
                
                $duration = $start->diffInDays($end);
                
                if ($duration > 365) {
                    $validator->errors()->add('date_end', 'La duración del viaje no puede exceder 365 días.');
                }
            }
        });
    }
}