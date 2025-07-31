<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\DeletedMeasureTypeDTO.php

namespace App\DTOs;

class DeletedMeasureTypeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,        // ✅ CAMBIO: description → name
        public readonly string $symbol,      // ✅ CAMBIO: acronym → symbol
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($measureType): self
    {
        return new self(
            id: $measureType->id,
            name: $measureType->name,        // ✅ CAMBIO
            symbol: $measureType->symbol,    // ✅ CAMBIO
            createdAt: $measureType->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $measureType->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $measureType->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}