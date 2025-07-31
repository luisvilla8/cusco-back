<?php

namespace App\DTOs\ProductCategory;

class ProductCategoryDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $productsCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($productCategory): self
    {
        return new self(
            id: $productCategory->id,
            name: $productCategory->name,
            description: $productCategory->description,
            productsCount: $productCategory->products_count ?? 0,
            createdAt: $productCategory->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $productCategory->updated_at?->format('Y-m-d H:i:s') ?? '',
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
            'products_count' => $this->productsCount,
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * ✅ SIMPLIFICADO: Solo verificar si tiene productos
     */
    public function canBeDeleted(): bool
    {
        return $this->productsCount === 0;
    }
}