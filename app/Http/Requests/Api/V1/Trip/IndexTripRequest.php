<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Trip\IndexTripRequest.php

namespace App\Http\Requests\Api\V1\Trip;

use Illuminate\Foundation\Http\FormRequest;

class IndexTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'agent_id' => 'nullable|exists:agents,id',
            'zone_id' => 'nullable|exists:zones,id',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:upcoming,in_progress,completed',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'sort_by' => 'nullable|in:id,name,code,date_start,date_end,total,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.exists' => 'El agente seleccionado no es válido',
            'zone_id.exists' => 'La zona seleccionada no es válida',
            'user_id.exists' => 'El usuario seleccionado no es válido',
            'status.in' => 'El estado debe ser: upcoming, in_progress o completed',
            'date_end.after_or_equal' => 'La fecha fin debe ser posterior o igual a la fecha inicio',
            'sort_by.in' => 'Campo de ordenamiento no válido',
            'sort_order.in' => 'Orden de clasificación debe ser asc o desc',
            'per_page.max' => 'Máximo 100 elementos por página',
        ];
    }

    /**
     * ✅ PREPARAR DATOS - SIGUIENDO PATRÓN DE USER
     */
    protected function prepareForValidation()
    {
        $user = auth()->user();
        
        // ✅ VERIFICACIÓN SIMPLE como en User
        if (!$user || !$user->role_id) {
            return;
        }
        
        // ✅ VERIFICAR ROL SIN USAR load() - usando relación directa
        $userRole = $user->role; // Esto carga automáticamente si no está cargado
        
        // Si no es admin, quitar filtro de user_id
        if (!$userRole || !in_array($userRole->name, ['Administrador', 'Super Admin'])) {
            $this->merge([
                'user_id' => null
            ]);
        }
    }
}