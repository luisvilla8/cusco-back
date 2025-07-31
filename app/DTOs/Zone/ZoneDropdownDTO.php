<?php

namespace App\DTOs\Zone;

class ZoneDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
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
        ];
    }
}