<?php

namespace App\Rules;

use App\Models\Product;

class ProductBusinessRules
{
    /**
     * ✅ VERIFICAR SI PUEDE ELIMINARSE
     */
    public static function canBeDeleted(Product $product): bool
    {
        return !$product->hasZonePrices() && !$product->hasTransactions();
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(Product $product): void
    {
        if ($product->hasZonePrices()) {
            throw new \InvalidArgumentException('No se puede eliminar un producto que tiene precios por zona asignados');
        }

        if ($product->hasTransactions()) {
            throw new \InvalidArgumentException('No se puede eliminar un producto que tiene transacciones registradas');
        }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(Product $product): void
    {
        if ($product->hasTransactionsInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente un producto que tiene historial de transacciones');
        }
    }

    /**
     * Validar stock
     */
    public static function validateStock(array $data): void
    {
        $stock = (float) ($data['stock'] ?? 0);
        $minStock = (float) ($data['min_stock'] ?? 0);
        $maxStock = (float) ($data['max_stock'] ?? 0);

        if ($stock < 0) {
            throw new \InvalidArgumentException('El stock no puede ser negativo');
        }

        if ($minStock < 0) {
            throw new \InvalidArgumentException('El stock mínimo no puede ser negativo');
        }

        if ($maxStock <= 0) {
            throw new \InvalidArgumentException('El stock máximo debe ser mayor a 0');
        }

        if ($minStock >= $maxStock) {
            throw new \InvalidArgumentException('El stock mínimo debe ser menor al stock máximo');
        }
    }

    /**
     * Validar movimiento de stock
     */
    public static function validateStockMovement(Product $product, float $quantity, string $type): void
    {
        if ($type === 'OUT' && $product->stock < $quantity) {
            throw new \InvalidArgumentException('No hay suficiente stock disponible para esta operación');
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a 0');
        }
    }
}