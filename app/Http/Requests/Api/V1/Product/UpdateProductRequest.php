<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Product\UpdateProductRequest.php

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id');

        // ✅ DEBUG: LOG LA VALIDACIÓN
        Log::info('=== UPDATE REQUEST RULES ===', [
            'product_id' => $productId,
            'all_input' => $this->all(),
            'method' => $this->method(),
            'content_type' => $this->header('Content-Type'),
        ]);

        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('products')->ignore($productId)
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->ignore($productId)
            ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_image' => 'nullable|boolean',
            'stock' => 'sometimes|numeric|min:0',
            'min_stock' => 'sometimes|numeric|min:0',
            'max_stock' => 'sometimes|numeric|min:1',
            'cost' => 'sometimes|numeric|min:0',
            'price' => 'sometimes|numeric|min:0',
            'measure_type_id' => 'sometimes|integer|exists:measure_types,id',
            'product_category_id' => 'sometimes|integer|exists:product_categories,id',
        ];
    }

    /**
     * ✅ NO OVERRIDE validated() - usar el default
     */
    public function messages(): array
    {
        return [
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp.',
            'image.max' => 'La imagen no puede pesar más de 2MB.',
            'remove_image.boolean' => 'El campo remove_image debe ser verdadero o falso.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'code.unique' => 'Este código ya está siendo usado por otro producto.',
            'barcode.unique' => 'Este código de barras ya está siendo usado por otro producto.',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            // ✅ VALIDACIONES DE NEGOCIO
            if (isset($data['min_stock']) && isset($data['max_stock'])) {
                if ((float)$data['min_stock'] >= (float)$data['max_stock']) {
                    $validator->errors()->add('min_stock', 'El stock mínimo debe ser menor al stock máximo.');
                }
            }

            if (isset($data['cost']) && isset($data['price'])) {
                if ((float)$data['cost'] > (float)$data['price']) {
                    $validator->errors()->add('price', 'El precio debe ser mayor o igual al costo.');
                }
            }
        });
    }
}