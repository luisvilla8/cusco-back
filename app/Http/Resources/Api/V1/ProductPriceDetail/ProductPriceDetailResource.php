<?php

namespace App\Http\Resources\Api\V1\ProductPriceDetail;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            
            // Comparación con precio base
            'base_price' => $this->product?->price ?? 0,
            'formatted_base_price' => 'S/ ' . number_format($this->product?->price ?? 0, 2),
            'price_difference' => $this->price_difference,
            'price_difference_percentage' => round($this->price_difference_percentage, 2),
            'is_higher_than_base' => $this->price > ($this->product?->price ?? 0),
            'is_lower_than_base' => $this->price < ($this->product?->price ?? 0),
            
            // Información del producto
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'description' => $this->product->description,
                    'base_price' => $this->product->price,
                    'formatted_base_price' => $this->product->formatted_price,
                    'stock' => $this->product->stock,
                    'category' => $this->product->category?->name,
                ];
            }),
            
            // Información de la zona
            'zone' => $this->whenLoaded('zone', function () {
                return [
                    'id' => $this->zone->id,
                    'name' => $this->zone->name,
                    'description' => $this->zone->description,
                ];
            }),
            
            // IDs para referencias
            'product_id' => $this->product_id,
            'zone_id' => $this->zone_id,
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}