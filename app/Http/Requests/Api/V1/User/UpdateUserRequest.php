<?php

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Aquí puedes agregar lógica de permisos
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('id');
        
        return [
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|max:255|unique:users,email,{$userId}",
            'password' => 'sometimes|nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|exists:roles,id',
            // ✅ ASEGURAR que zone_ids sea opcional y array
            'zone_ids' => 'nullable|array',
            'zone_ids.*' => 'exists:zones,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'email.email' => 'El email debe tener un formato válido',
            'email.unique' => 'Este email ya está registrado',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'role_id.exists' => 'El rol seleccionado no es válido',
            'zone_ids.array' => 'Las zonas deben ser un array',
            'zone_ids.*.exists' => 'Una o más zonas seleccionadas no son válidas',
        ];
    }

    /**
     * ✅ PREPARAR datos para asegurar que zone_ids esté siempre presente si se envía
     */
    protected function prepareForValidation()
    {
        // Si zone_ids está presente pero está vacío, convertirlo a array vacío
        if ($this->has('zone_ids') && is_null($this->zone_ids)) {
            $this->merge(['zone_ids' => []]);
        }
    }

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