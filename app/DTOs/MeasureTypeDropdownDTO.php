<?php

namespace App\DTOs;

class MeasureTypeDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,       // ✅ CAMBIO: description → name
        public readonly string $symbol,     // ✅ CAMBIO: acronym → symbol
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($measureType): self
    {
        return new self(
            id: $measureType->id,
            name: $measureType->name,       // ✅ CAMBIO
            symbol: $measureType->symbol,   // ✅ CAMBIO
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,                              // ✅ CAMBIO
            'symbol' => $this->symbol,                          // ✅ CAMBIO
            'display_name' => "{$this->name} ({$this->symbol})", // ✅ CAMBIO
        ];
    }
}