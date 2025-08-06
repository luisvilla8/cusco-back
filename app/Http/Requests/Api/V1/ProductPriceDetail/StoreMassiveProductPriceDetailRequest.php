<?php

namespace App\Http\Requests\Api\V1\ProductPriceDetail;

use Illuminate\Foundation\Http\FormRequest;

class StoreMassiveProductPriceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'prices' => 'required|array|min:1',
            'prices.*.zone_id' => 'required|exists:zones,id',
            'prices.*.price' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio',
            'product_id.exists' => 'El producto seleccionado no es válido',
            'prices.required' => 'Los precios son obligatorios',
            'prices.array' => 'Los precios deben ser un array',
            'prices.min' => 'Debe especificar al menos un precio',
            'prices.*.zone_id.required' => 'La zona es obligatoria para cada precio',
            'prices.*.zone_id.exists' => 'Una o más zonas seleccionadas no son válidas',
            'prices.*.price.required' => 'El precio es obligatorio para cada zona',
            'prices.*.price.numeric' => 'El precio debe ser un número',
            'prices.*.price.min' => 'El precio debe ser mayor a 0',
        ];
    }
}