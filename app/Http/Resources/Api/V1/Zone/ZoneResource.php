<?php

namespace App\Http\Resources\Api\V1\Zone;

use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location_url' => $this->location_url,
            'has_location' => !empty($this->location_url),
            
            // Estadísticas
            'products_with_prices_count' => $this->whenLoaded('productPriceDetails', function () {
                return $this->productPriceDetails->unique('product_id')->count();
            }, $this->products_with_prices_count ?? 0),
            
            'has_prices' => $this->whenLoaded('productPriceDetails', function () {
                return $this->productPriceDetails->isNotEmpty();
            }, $this->hasPrices()),
            
            // Relaciones
            'product_prices' => $this->whenLoaded('productPriceDetails', function () {
                return $this->productPriceDetails->map(function ($priceDetail) {
                    return [
                        'id' => $priceDetail->id,
                        'product_id' => $priceDetail->product_id,
                        'product_name' => $priceDetail->product?->description,
                        'price' => $priceDetail->price,
                        'formatted_price' => 'S/ ' . number_format($priceDetail->price, 2),
                        'created_at' => $priceDetail->created_at,
                        'updated_at' => $priceDetail->updated_at,
                    ];
                });
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}