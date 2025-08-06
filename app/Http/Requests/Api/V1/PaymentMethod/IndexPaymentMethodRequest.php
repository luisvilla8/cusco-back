<?php

namespace App\Http\Requests\Api\V1\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;

class IndexPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:name,code,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'El término de búsqueda debe ser una cadena de texto',
            'search.max' => 'El término de búsqueda no puede exceder 255 caracteres',
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, code o created_at',
            'sort_order.in' => 'El orden debe ser: asc o desc',
            'per_page.integer' => 'Los elementos por página deben ser un número entero',
            'per_page.min' => 'Debe mostrar al menos 1 elemento por página',
            'per_page.max' => 'No se pueden mostrar más de 100 elementos por página',
        ];
    }
}