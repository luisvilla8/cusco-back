<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Product\ProductDropdownDTO.php

namespace App\DTOs\Product;

class ProductDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $barcode,
        public readonly float $stock,
        public readonly ?string $measureTypeSymbol,
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
            measureTypeSymbol: $product->measureType?->symbol ?? $product->measure_type_symbol ?? null,
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
            'measure_type_symbol' => $this->measureTypeSymbol,
            'display_name' => $this->getDisplayName(),
        ];
    }

    /**
     * Get formatted display name
     */
    private function getDisplayName(): string
    {
        $parts = [$this->name];
        
        if ($this->code) {
            $parts[] = "({$this->code})";
        }
        
        if ($this->measureTypeSymbol) {
            $parts[] = "[{$this->measureTypeSymbol}]";
        }
        
        return implode(' ', $parts);
    }
}