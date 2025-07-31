<?php

namespace App\DTOs\Zone;

class DeletedZoneDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $locationUrl,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt,
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
            createdAt: $zone->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $zone->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $zone->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}