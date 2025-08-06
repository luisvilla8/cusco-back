<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\User\IndexUserRequest.php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Aquí puedes agregar lógica de permisos
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'role_id' => 'sometimes|integer|exists:roles,id',
            'zone_id' => 'sometimes|integer|exists:zones,id',
            'sort_by' => 'sometimes|string|in:name,email,code,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser una cadena de texto',
            'search.max' => 'El término de búsqueda no puede exceder 255 caracteres',
            'role_id.exists' => 'El rol seleccionado no es válido',
            'zone_id.exists' => 'La zona seleccionada no es válida',
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, email, code o created_at',
            'sort_order.in' => 'El orden debe ser: asc o desc',
            'per_page.integer' => 'Los elementos por página deben ser un número entero',
            'per_page.min' => 'Debe mostrar al menos 1 elemento por página',
            'per_page.max' => 'No se pueden mostrar más de 100 elementos por página',
        ];
    }
}