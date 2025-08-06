<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\ProductPriceDetail\ProductPriceDetailDTO.php

namespace App\DTOs\ProductPriceDetail;

class ProductPriceDetailDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly float $price,
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $productCode,
        public readonly float $productDefaultPrice,
        public readonly int $zoneId,
        public readonly string $zoneName,
        public readonly string $zoneCode,
        public readonly bool $isDefaultPrice,
        public readonly float $priceDifference,
        public readonly string $priceDifferencePercentage,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromModel($priceDetail): self
    {
        $productDefaultPrice = $priceDetail->product->price ?? 0;
        $priceDifference = $priceDetail->price - $productDefaultPrice;
        $priceDifferencePercentage = $productDefaultPrice > 0 
            ? round(($priceDifference / $productDefaultPrice) * 100, 2)
            : 0;

        return new self(
            id: $priceDetail->id,
            code: $priceDetail->code,
            price: (float) $priceDetail->price,
            productId: $priceDetail->product_id,
            productName: $priceDetail->product?->name ?? 'Producto eliminado',
            productCode: $priceDetail->product?->code ?? 'N/A',
            productDefaultPrice: $productDefaultPrice,
            zoneId: $priceDetail->zone_id,
            zoneName: $priceDetail->zone?->name ?? 'Zona eliminada',
            zoneCode: $priceDetail->zone?->code ?? 'N/A',
            isDefaultPrice: abs($priceDifference) < 0.01,
            priceDifference: $priceDifference,
            priceDifferencePercentage: $priceDifferencePercentage > 0 ? "+{$priceDifferencePercentage}%" : "{$priceDifferencePercentage}%",
            createdAt: $priceDetail->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $priceDetail->updated_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'price' => $this->price,
            'product' => [
                'id' => $this->productId,
                'name' => $this->productName,
                'code' => $this->productCode,
                'default_price' => $this->productDefaultPrice,
            ],
            'zone' => [
                'id' => $this->zoneId,
                'name' => $this->zoneName,
                'code' => $this->zoneCode,
            ],
            'is_default_price' => $this->isDefaultPrice,
            'price_difference' => $this->priceDifference,
            'price_difference_percentage' => $this->priceDifferencePercentage,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}