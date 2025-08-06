<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ProductPriceService.php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceDetail;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

class ProductPriceService
{
    /**
     * Obtener el precio de un producto para una zona específica
     */
    public static function getProductPriceForZone(int $productId, ?int $zoneId): array
    {
        $cacheKey = "product_price_{$productId}_zone_{$zoneId}";
        
        return Cache::remember($cacheKey, 300, function () use ($productId, $zoneId) {
            $product = Product::find($productId);
            
            if (!$product) {
                return [
                    'price' => 0,
                    'source' => 'not_found',
                    'source_description' => 'Producto no encontrado',
                    'zone_price_detail_id' => null
                ];
            }
            
            // Si no hay zona, usar precio base
            if (!$zoneId) {
                return [
                    'price' => (float) $product->price,
                    'source' => 'base_price',
                    'source_description' => 'Precio base del producto',
                    'zone_price_detail_id' => null
                ];
            }
            
            // Buscar precio específico para la zona
            $zonePriceDetail = ProductPriceDetail::active()
                ->where('product_id', $productId)
                ->where('zone_id', $zoneId)
                ->first();
            
            if ($zonePriceDetail) {
                $zone = Zone::find($zoneId);
                return [
                    'price' => (float) $zonePriceDetail->price,
                    'source' => 'zone_price',
                    'source_description' => "Precio para zona {$zone?->name}",
                    'zone_price_detail_id' => $zonePriceDetail->id
                ];
            }
            
            // Si no hay precio para la zona, usar precio base
            return [
                'price' => (float) $product->price,
                'source' => 'base_price_fallback',
                'source_description' => 'Precio base (no definido para esta zona)',
                'zone_price_detail_id' => null
            ];
        });
    }
    
    /**
     * Obtener precios para múltiples productos en una zona
     */
    public static function getMultipleProductPricesForZone(array $productIds, ?int $zoneId): array
    {
        $prices = [];
        
        foreach ($productIds as $productId) {
            $prices[$productId] = self::getProductPriceForZone($productId, $zoneId);
        }
        
        return $prices;
    }
    
    /**
     * Validar precios enviados contra precios actuales
     */
    public static function validatePrices(array $details, ?int $zoneId): array
    {
        $errors = [];
        
        foreach ($details as $index => $detail) {
            $productId = $detail['product_id'] ?? null;
            $sentPrice = (float) ($detail['price'] ?? 0);
            
            if (!$productId) {
                continue;
            }
            
            $priceInfo = self::getProductPriceForZone($productId, $zoneId);
            $currentPrice = $priceInfo['price'];
            
            if (abs($sentPrice - $currentPrice) > 0.01) {
                $product = Product::find($productId);
                $errors["details.{$index}.price"] = [
                    "El precio del producto '{$product?->name}' ha cambiado. ".
                    "Precio actual: S/ {$currentPrice} ({$priceInfo['source_description']}), ".
                    "precio enviado: S/ {$sentPrice}. Por favor actualiza los precios."
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * Limpiar caché de precios para un producto
     */
    public static function clearProductPriceCache(int $productId): void
    {
        $zones = Zone::active()->pluck('id');
        
        // Limpiar caché para todas las zonas
        foreach ($zones as $zoneId) {
            Cache::forget("product_price_{$productId}_zone_{$zoneId}");
        }
        
        // También limpiar para sin zona
        Cache::forget("product_price_{$productId}_zone_");
    }
    
    /**
     * Limpiar caché de precios para una zona
     */
    public static function clearZonePriceCache(int $zoneId): void
    {
        $products = Product::active()->pluck('id');
        
        foreach ($products as $productId) {
            Cache::forget("product_price_{$productId}_zone_{$zoneId}");
        }
    }
}