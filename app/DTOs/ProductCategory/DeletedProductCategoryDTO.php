<?php

namespace App\DTOs\ProductCategory;

class DeletedProductCategoryDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt,
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
            createdAt: $productCategory->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $productCategory->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $productCategory->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}