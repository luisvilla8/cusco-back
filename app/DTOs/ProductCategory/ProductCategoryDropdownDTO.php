<?php

namespace App\DTOs\ProductCategory;

class ProductCategoryDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
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