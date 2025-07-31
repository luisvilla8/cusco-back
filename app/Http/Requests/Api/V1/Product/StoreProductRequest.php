<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Product\StoreProductRequest.php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => 'required|string|max:50|unique:products,code',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // ✅ ARCHIVO DE IMAGEN
            'stock' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'max_stock' => 'required|numeric|min:1',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'measure_type_id' => 'required|integer|exists:measure_types,id',
            'product_category_id' => 'required|integer|exists:product_categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres.',
            'code.required' => 'El código del producto es obligatorio.',
            'code.unique' => 'Ya existe un producto con este código.',
            'code.max' => 'El código no puede tener más de 50 caracteres.',
            'barcode.unique' => 'Ya existe un producto con este código de barras.',
            'barcode.max' => 'El código de barras no puede tener más de 100 caracteres.',
            
            // ✅ VALIDACIONES DE IMAGEN
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp.',
            'image.max' => 'La imagen no puede pesar más de 2MB.',
            
            'stock.required' => 'El stock es obligatorio.',
            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock no puede ser negativo.',
            'min_stock.required' => 'El stock mínimo es obligatorio.',
            'min_stock.numeric' => 'El stock mínimo debe ser un número.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'max_stock.required' => 'El stock máximo es obligatorio.',
            'max_stock.numeric' => 'El stock máximo debe ser un número.',
            'max_stock.min' => 'El stock máximo debe ser mayor a 0.',
            'cost.required' => 'El costo es obligatorio.',
            'cost.numeric' => 'El costo debe ser un número.',
            'cost.min' => 'El costo no puede ser negativo.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
            'measure_type_id.required' => 'El tipo de medida es obligatorio.',
            'measure_type_id.exists' => 'El tipo de medida seleccionado no existe.',
            'product_category_id.required' => 'La categoría del producto es obligatoria.',
            'product_category_id.exists' => 'La categoría del producto seleccionada no existe.',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            
            if (isset($data['min_stock']) && isset($data['max_stock'])) {
                if ($data['min_stock'] >= $data['max_stock']) {
                    $validator->errors()->add('min_stock', 'El stock mínimo debe ser menor al stock máximo.');
                }
            }

            if (isset($data['cost']) && isset($data['price'])) {
                if ($data['cost'] > $data['price']) {
                    $validator->errors()->add('price', 'El precio debe ser mayor o igual al costo.');
                }
            }
        });
    }
}