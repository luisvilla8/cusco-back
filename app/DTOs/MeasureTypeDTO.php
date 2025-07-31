<?php

namespace App\DTOs;

class MeasureTypeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,          
        public readonly string $symbol,        
        public readonly ?string $description,  
        public readonly int $productsCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($measureType): self
    {
        return new self(
            id: $measureType->id,
            name: $measureType->name,                           
            symbol: $measureType->symbol,                      
            description: $measureType->description ?? null,     
            productsCount: $measureType->products_count ?? 0,
            createdAt: $measureType->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $measureType->updated_at?->format('Y-m-d H:i:s') ?? '',
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
            'symbol' => $this->symbol,                          
            'description' => $this->description,                
            'products_count' => $this->productsCount,
            'can_be_deleted' => $this->canBeDeleted(),
            'display_name' => $this->getDisplayName(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * ✅ SIMPLIFICADO: Solo verifica si tiene productos
     */
    public function canBeDeleted(): bool
    {
        return $this->productsCount === 0;
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return "{$this->name} ({$this->symbol})";             
    }
}