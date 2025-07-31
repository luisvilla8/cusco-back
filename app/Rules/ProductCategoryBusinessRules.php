<?php

namespace App\Rules;

use App\Models\ProductCategory;

class ProductCategoryBusinessRules
{
    /**
     * ✅ SIMPLIFICADO: Solo verificar si tiene productos
     */
    public static function canBeDeleted(ProductCategory $productCategory): bool
    {
        return !$productCategory->hasProducts();
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(ProductCategory $productCategory): void
    {
        if ($productCategory->hasProducts()) {
            throw new \InvalidArgumentException('No se puede eliminar una categoría que tiene productos asignados');
        }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(ProductCategory $productCategory): void
    {
        if ($productCategory->hasProductsInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente una categoría que tiene historial de productos');
        }
    }
}