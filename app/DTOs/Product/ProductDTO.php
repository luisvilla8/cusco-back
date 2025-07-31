<?php

namespace App\DTOs\Product;

class ProductDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $code,
        public readonly ?string $barcode,
        public readonly ?string $imageUrl,
        public readonly ?string $thumbnailUrl,
        public readonly float $stock,
        public readonly float $minStock,
        public readonly float $maxStock,
        public readonly float $cost,
        public readonly float $price,
        public readonly int $measureTypeId,
        public readonly ?string $measureTypeName,
        public readonly ?string $measureTypeSymbol,
        public readonly int $productCategoryId,
        public readonly ?string $productCategoryName,
        public readonly int $zonePricesCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($product): self
    {
        // ✅ GENERAR URLs COMPLETAS PARA IMÁGENES
        $imageUrl = null;
        $thumbnailUrl = null;
        
        if ($product->image_url) {
            $imageUrl = self::getFullImageUrl($product->image_url);
            // Generar thumbnail URL desde la imagen redimensionada
            $thumbnailUrl = str_replace('/resized/', '/thumbnails/', $imageUrl);
        }

        return new self(
            id: $product->id,
            name: $product->name,
            description: $product->description,
            code: $product->code,
            barcode: $product->barcode,
            imageUrl: $imageUrl,
            thumbnailUrl: $thumbnailUrl,
            stock: (float) $product->stock,
            minStock: (float) $product->min_stock,
            maxStock: (float) $product->max_stock,
            cost: (float) $product->cost,
            price: (float) $product->price,
            measureTypeId: $product->measure_type_id,
            measureTypeName: $product->measureType?->name ?? null,
            measureTypeSymbol: $product->measureType?->symbol ?? null,
            productCategoryId: $product->product_category_id,
            productCategoryName: $product->productCategory?->name ?? null,
            zonePricesCount: $product->product_price_details_count ?? 0,
            createdAt: $product->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $product->updated_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    /**
     * ✅ GENERAR URL COMPLETA PARA IMAGEN
     */
    private static function getFullImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) return null;
        
        // Si ya tiene el dominio, devolverla tal como está
        if (str_starts_with($imagePath, 'http')) {
            return $imagePath;
        }
        
        // Si no empieza con /storage/, agregarle
        if (!str_starts_with($imagePath, '/storage/')) {
            $imagePath = '/storage/' . ltrim($imagePath, '/');
        }
        
        // Agregar el dominio base de la aplicación
        $appUrl = rtrim(config('app.url'), '/');
        return $appUrl . $imagePath;
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
            'code' => $this->code,
            'barcode' => $this->barcode,
            'image_url' => $this->imageUrl,
            'thumbnail_url' => $this->thumbnailUrl,
            'stock' => $this->stock,
            'min_stock' => $this->minStock,
            'max_stock' => $this->maxStock,
            'cost' => $this->cost,
            'price' => $this->price,
            'measure_type_id' => $this->measureTypeId,
            'measure_type_name' => $this->measureTypeName,
            'measure_type_symbol' => $this->measureTypeSymbol,
            'product_category_id' => $this->productCategoryId,
            'product_category_name' => $this->productCategoryName,
            'zone_prices_count' => $this->zonePricesCount,
            'stock_status' => $this->getStockStatus(),
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Get stock status
     */
    public function getStockStatus(): string
    {
        if ($this->stock <= 0) return 'SIN_STOCK';
        if ($this->stock <= $this->minStock) return 'STOCK_BAJO';
        if ($this->stock >= $this->maxStock) return 'STOCK_ALTO';
        return 'STOCK_NORMAL';
    }

    /**
     * ✅ SIMPLIFICADO: Solo verificar si tiene precios por zona
     */
    public function canBeDeleted(): bool
    {
        return $this->zonePricesCount === 0;
    }
}