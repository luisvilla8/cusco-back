<?php

namespace App\DTOs\ProductPriceDetail;

class ProductZonePricesDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $productCode,
        public readonly float $productDefaultPrice,
        public readonly array $zonePrices,
        public readonly array $missingZones,
        public readonly int $totalZones,
        public readonly int $configuredZones,
    ) {}

    public static function fromData(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            productName: $data['product_name'],
            productCode: $data['product_code'],
            productDefaultPrice: $data['product_default_price'],
            zonePrices: $data['zone_prices'],
            missingZones: $data['missing_zones'],
            totalZones: $data['total_zones'],
            configuredZones: $data['configured_zones'],
        );
    }

    public function toArray(): array
    {
        return [
            'product' => [
                'id' => $this->productId,
                'name' => $this->productName,
                'code' => $this->productCode,
                'default_price' => $this->productDefaultPrice,
            ],
            'zone_prices' => $this->zonePrices,
            'missing_zones' => $this->missingZones,
            'summary' => [
                'total_zones' => $this->totalZones,
                'configured_zones' => $this->configuredZones,
                'missing_zones' => count($this->missingZones),
                'coverage_percentage' => $this->totalZones > 0 
                    ? round(($this->configuredZones / $this->totalZones) * 100, 2) 
                    : 0,
            ],
        ];
    }
}