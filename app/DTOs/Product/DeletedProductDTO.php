<?php

namespace App\DTOs\Product;

class DeletedProductDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $barcode,
        public readonly float $stock,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            code: $product->code,
            barcode: $product->barcode,
            stock: (float) $product->stock,
            createdAt: $product->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $product->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $product->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
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
            'code' => $this->code,
            'barcode' => $this->barcode,
            'stock' => $this->stock,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}