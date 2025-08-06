<?php

namespace App\Http\Requests\Api\V1\ProductPriceDetail;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPriceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        
        return [
            'price' => 'sometimes|required|numeric|min:0.01',
            'zone_id' => 'sometimes|required|exists:zones,id',
            'product_id' => 'sometimes|required|exists:products,id',
        ];
    }

    public function messages(): array
    {
        return [
            'price.required' => 'El precio es obligatorio',
            'price.numeric' => 'El precio debe ser un número',
            'price.min' => 'El precio debe ser mayor a 0',
            'zone_id.required' => 'La zona es obligatoria',
            'zone_id.exists' => 'La zona seleccionada no es válida',
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto seleccionado no es válido',
        ];
    }
}