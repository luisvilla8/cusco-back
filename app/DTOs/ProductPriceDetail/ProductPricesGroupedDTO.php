<?php

namespace App\DTOs\ProductPriceDetail;

class ProductPricesGroupedDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $productCode,
        public readonly float $productDefaultPrice,
        public readonly array $zonePrices,
        public readonly int $totalZones,
        public readonly int $configuredZones,
        public readonly float $coveragePercentage,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromData(array $data): self
    {
        $totalZones = count($data['zone_prices']);
        $configuredZones = count(array_filter($data['zone_prices'], fn($zone) => isset($zone['id'])));
        $coveragePercentage = $totalZones > 0 ? round(($configuredZones / $totalZones) * 100, 2) : 0;

        // Obtener fechas más recientes
        $dates = collect($data['zone_prices'])
            ->filter(fn($zone) => isset($zone['created_at']))
            ->map(fn($zone) => [
                'created_at' => $zone['created_at'],
                'updated_at' => $zone['updated_at']
            ]);

        $latestCreated = $dates->max('created_at') ?? '';
        $latestUpdated = $dates->max('updated_at') ?? '';

        return new self(
            productId: $data['product']['id'],
            productName: $data['product']['name'],
            productCode: $data['product']['code'],
            productDefaultPrice: $data['product']['default_price'],
            zonePrices: $data['zone_prices'],
            totalZones: $totalZones,
            configuredZones: $configuredZones,
            coveragePercentage: $coveragePercentage,
            createdAt: $latestCreated,
            updatedAt: $latestUpdated,
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
            'summary' => [
                'total_zones' => $this->totalZones,
                'configured_zones' => $this->configuredZones,
                'missing_zones' => $this->totalZones - $this->configuredZones,
                'coverage_percentage' => $this->coveragePercentage,
            ],
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}