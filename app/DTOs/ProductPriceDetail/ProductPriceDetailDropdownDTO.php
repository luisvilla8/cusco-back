<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\ProductPriceDetail\ProductPriceDetailDropdownDTO.php

namespace App\DTOs\ProductPriceDetail;

class ProductPriceDetailDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly float $price,
        public readonly string $displayName,
        public readonly string $productName,
        public readonly string $zoneName,
    ) {}

    public static function fromModel($priceDetail): self
    {
        $displayName = "{$priceDetail->product?->name} - {$priceDetail->zone?->name} (S/ {$priceDetail->price})";
        
        return new self(
            id: $priceDetail->id,
            code: $priceDetail->code,
            price: (float) $priceDetail->price,
            displayName: $displayName,
            productName: $priceDetail->product?->name ?? 'N/A',
            zoneName: $priceDetail->zone?->name ?? 'N/A',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'price' => $this->price,
            'display_name' => $this->displayName,
            'product_name' => $this->productName,
            'zone_name' => $this->zoneName,
        ];
    }
}