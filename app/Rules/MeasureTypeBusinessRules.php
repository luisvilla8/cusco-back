<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Rules\MeasureTypeBusinessRules.php

namespace App\Rules;

use App\Models\MeasureType;

class MeasureTypeBusinessRules
{
    
    public static function canBeDeleted(MeasureType $measureType): bool
    {
        return !$measureType->hasProducts();
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(MeasureType $measureType): void
    {
        if ($measureType->hasProducts()) {
            throw new \InvalidArgumentException('No se puede eliminar una unidad de medida que tiene productos asignados');
        }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(MeasureType $measureType): void
    {
        if ($measureType->hasProductsInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente una unidad de medida que tiene historial de productos');
        }
    }
}