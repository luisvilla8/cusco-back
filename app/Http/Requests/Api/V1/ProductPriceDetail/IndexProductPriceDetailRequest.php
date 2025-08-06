<?php

namespace App\Http\Requests\Api\V1\ProductPriceDetail;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductPriceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'product_id' => 'nullable|exists:products,id',
            'zone_id' => 'nullable|exists:zones,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,price,product_name,zone_name,created_at,updated_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'La búsqueda no puede exceder 255 caracteres',
            'product_id.exists' => 'El producto seleccionado no es válido',
            'zone_id.exists' => 'La zona seleccionada no es válida',
            'per_page.integer' => 'La cantidad por página debe ser un número',
            'per_page.min' => 'La cantidad por página debe ser al menos 1',
            'per_page.max' => 'La cantidad por página no puede exceder 100',
            'sort_by.in' => 'El campo de ordenamiento no es válido',
            'sort_order.in' => 'El orden debe ser asc o desc',
        ];
    }
}