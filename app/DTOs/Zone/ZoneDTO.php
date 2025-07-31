<?php

namespace App\DTOs\Zone;

class ZoneDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $locationUrl,
        public readonly int $productPricesCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($zone): self
    {
        return new self(
            id: $zone->id,
            name: $zone->name,
            description: $zone->description,
            locationUrl: $zone->location_url,
            productPricesCount: $zone->product_price_details_count ?? 0,
            createdAt: $zone->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $zone->updated_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location_url' => $this->locationUrl,
            'product_prices_count' => $this->productPricesCount,
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * ✅ SIMPLIFICADO: Solo verificar si tiene precios de productos
     */
    public function canBeDeleted(): bool
    {
        return $this->productPricesCount === 0;
    }
}