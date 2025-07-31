<?php

namespace App\Rules;

use App\Models\Zone;

class ZoneBusinessRules
{

    public static function canBeDeleted(Zone $zone): bool
    {
        return !$zone->hasPrices();
    }

    /**
     * Verificar reglas de eliminación (throws exception)
     */
    public static function validateDeletion(Zone $zone): void
    {
        if ($zone->hasPrices()) {
            throw new \InvalidArgumentException('No se puede eliminar una zona que tiene precios de productos asignados');
        }
    }

    /**
     * Verificar reglas de eliminación permanente
     */
    public static function validateForceDeletion(Zone $zone): void
    {
        if ($zone->hasPricesInHistory()) {
            throw new \InvalidArgumentException('No se puede eliminar permanentemente una zona que tiene historial de precios');
        }
    }

    /**
     * Validar URL de ubicación
     */
    public static function validateLocationUrl(?string $locationUrl): void
    {
        if ($locationUrl && !filter_var($locationUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('La URL de ubicación no tiene un formato válido');
        }
    }
}