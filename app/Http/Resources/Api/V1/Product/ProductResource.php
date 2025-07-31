<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Resources\Api\V1\Product\ProductResource.php

namespace App\Http\Resources\Api\V1\Product;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\ProductCategory\ProductCategoryResource;
use App\Http\Resources\Api\V1\MeasureType\MeasureTypeResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        // Obtener ruta original una sola vez
        $originalImagePath = $this->getOriginal('image_url');
        
        return [
            'id' => $this->id,
            'description' => $this->description,
            'stock' => $this->stock,
            'formatted_stock' => $this->formatted_stock,
            'cost' => $this->cost,
            'formatted_cost' => $this->formatted_cost,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'profit_margin' => $this->profit_margin,
            
            // MANEJO OPTIMIZADO DE IMAGEN
            'image_path' => $originalImagePath, // Ruta relativa original
            'image_url' => $originalImagePath ? asset('storage/' . $originalImagePath) : null,
            'has_image' => !empty($originalImagePath),
            
            'is_in_stock' => $this->isInStock(),
            
            // Relaciones
            'category' => $this->whenLoaded('category', function () {
                return new ProductCategoryResource($this->category);
            }),
            'measure_type' => $this->whenLoaded('measureType', function () {
                return new MeasureTypeResource($this->measureType);
            }),
            'price_details' => $this->whenLoaded('priceDetails', function () {
                return $this->priceDetails->map(function ($priceDetail) {
                    return [
                        'id' => $priceDetail->id,
                        'price' => $priceDetail->price,
                        'formatted_price' => $priceDetail->formatted_price,
                        'zone' => [
                            'id' => $priceDetail->zone->id,
                            'name' => $priceDetail->zone->name,
                        ],
                    ];
                });
            }),
            
            // IDs para referencias
            'category_id' => $this->category_id,
            'measure_type_id' => $this->measure_type_id,
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
