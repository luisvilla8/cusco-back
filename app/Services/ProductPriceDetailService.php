<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ProductPriceDetailService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\ProductPriceDetailMapper;
use App\Models\Product;
use App\Models\ProductPriceDetail;
use App\Models\Zone;
use App\Repositories\ProductPriceDetailRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductPriceDetailService
{
    public function __construct(
        private ProductPriceDetailRepository $repository
    ) {}

    public function getAllPriceDetails(array $filters): array
    {
        try {
            $user = Auth::user();
            $allowedZoneIds = $this->getAllowedZoneIds($user);

            $paginatedPrices = $this->repository->getAllActiveGroupedByProduct($filters, $allowedZoneIds);

            $responseData = ProductPriceDetailMapper::paginatedGroupedToDTOs($paginatedPrices);

            return ResponseHelper::paginated(
                $responseData['data'],
                $paginatedPrices,
                'Precios de productos obtenidos exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al obtener los precios de productos: ' . $e->getMessage());
        }
    }

    public function getFlatPriceDetailsList(array $filters): array
    {
        try {
            $user = Auth::user();
            $allowedZoneIds = $this->getAllowedZoneIds($user);

            $paginatedPrices = $this->repository->getAllActiveWithPagination($filters, $allowedZoneIds);

            $responseData = ProductPriceDetailMapper::paginatedToDTOs($paginatedPrices);

            return ResponseHelper::paginated(
                $responseData['data'],
                $paginatedPrices,
                'Lista plana de precios obtenida exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al obtener la lista de precios: ' . $e->getMessage());
        }
    }

    public function getPriceDetail(int $id): array
    {
        try {
            $priceDetail = $this->repository->findActiveWithRelations($id);

            if (!$priceDetail) {
                return ResponseHelper::notFound('Precio no encontrado');
            }

            $user = Auth::user();
            if (!$this->canAccessZone($user, $priceDetail->zone_id)) {
                return ResponseHelper::forbidden('No tienes acceso a esta zona');
            }

            $priceDetailDTO = ProductPriceDetailMapper::modelToDTO($priceDetail);

            return ResponseHelper::success(
                $priceDetailDTO->toArray(),
                'Precio obtenido exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al obtener el precio: ' . $e->getMessage());
        }
    }

    public function createMassivePrices(array $data): array
    {
        try {
            $productId = $data['product_id'];
            $prices = $data['prices'];

            $product = Product::active()->find($productId);
            if (!$product) {
                return ResponseHelper::notFound('Producto no encontrado');
            }

            $user = Auth::user();
            $requestedZoneIds = collect($prices)->pluck('zone_id')->toArray();
            
            if (!$this->canAccessZones($user, $requestedZoneIds)) {
                return ResponseHelper::forbidden('No tienes acceso a algunas de las zonas especificadas');
            }

            $results = $this->repository->createMassivePrices($productId, $prices);

            $this->clearProductPriceCache($productId);

            $responseData = ProductPriceDetailMapper::massiveCreateResultsToArrays($results);

            $message = 'Precios masivos procesados exitosamente. ';
            $message .= 'Creados: ' . count($results['created']) . ', ';
            $message .= 'Omitidos: ' . count($results['skipped']) . ', ';
            $message .= 'Errores: ' . count($results['errors']);

            return ResponseHelper::success($responseData, $message);

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al crear precios masivos: ' . $e->getMessage());
        }
    }

    public function updateMassivePrices(array $data): array
    {
        try {
            $productId = $data['product_id'];
            $prices = $data['prices'];

            $product = Product::active()->find($productId);
            if (!$product) {
                return ResponseHelper::notFound('Producto no encontrado');
            }

            $user = Auth::user();
            $requestedZoneIds = collect($prices)->pluck('zone_id')->toArray();
            
            if (!$this->canAccessZones($user, $requestedZoneIds)) {
                return ResponseHelper::forbidden('No tienes acceso a algunas de las zonas especificadas');
            }

            $results = $this->repository->updateMassivePrices($productId, $prices);

            $this->clearProductPriceCache($productId);

            $responseData = ProductPriceDetailMapper::massiveUpdateResultsToArrays($results);

            $message = 'Precios masivos actualizados exitosamente. ';
            $message .= 'Actualizados: ' . count($results['updated']) . ', ';
            $message .= 'No encontrados: ' . count($results['not_found']) . ', ';
            $message .= 'Errores: ' . count($results['errors']);

            return ResponseHelper::success($responseData, $message);

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al actualizar precios masivos: ' . $e->getMessage());
        }
    }

    public function getProductZonePrices(int $productId): array
    {
        try {
            $product = Product::active()->find($productId);
            if (!$product) {
                return ResponseHelper::notFound('Producto no encontrado');
            }

            $user = Auth::user();
            $allowedZoneIds = $this->getAllowedZoneIds($user);

            $productZonePrices = $this->repository->getProductZonePrices($productId, $allowedZoneIds);

            $productZonePricesDTO = ProductPriceDetailMapper::productZonePricesToDTO($productZonePrices);

            return ResponseHelper::success(
                $productZonePricesDTO->toArray(),
                'Precios por zona del producto obtenidos exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al obtener precios por zona: ' . $e->getMessage());
        }
    }

    public function clearProductPrices(int $productId): array
    {
        try {
            $product = Product::active()->find($productId);
            if (!$product) {
                return ResponseHelper::notFound('Producto no encontrado');
            }

            $user = Auth::user();
            
            if ($user->hasRole('Vendedor')) {
                return ResponseHelper::forbidden('No tienes permisos para limpiar precios de productos');
            }

            $deletedCount = $this->repository->clearProductPrices($productId);

            $this->clearProductPriceCache($productId);

            return ResponseHelper::success(
                ['deleted_count' => $deletedCount],
                "Se eliminaron {$deletedCount} precios del producto {$product->name}"
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al limpiar precios del producto: ' . $e->getMessage());
        }
    }

    public function updatePriceDetail(int $id, array $data): array
    {
        try {
            $priceDetail = $this->repository->findActiveWithRelations($id);

            if (!$priceDetail) {
                return ResponseHelper::notFound('Precio no encontrado');
            }

            $user = Auth::user();
            if (!$this->canAccessZone($user, $priceDetail->zone_id)) {
                return ResponseHelper::forbidden('No tienes acceso a esta zona');
            }

            $updatedPriceDetail = $this->repository->update($id, $data);

            if (!$updatedPriceDetail) {
                return ResponseHelper::internalServerError('Error al actualizar el precio');
            }

            $this->clearProductPriceCache($updatedPriceDetail->product_id);

            $priceDetailDTO = ProductPriceDetailMapper::modelToDTO($updatedPriceDetail);

            return ResponseHelper::success(
                $priceDetailDTO->toArray(),
                'Precio actualizado exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al actualizar el precio: ' . $e->getMessage());
        }
    }

    public function deletePriceDetail(int $id): array
    {
        try {
            $priceDetail = $this->repository->findActiveWithRelations($id);

            if (!$priceDetail) {
                return ResponseHelper::notFound('Precio no encontrado');
            }

            $user = Auth::user();
            if (!$this->canAccessZone($user, $priceDetail->zone_id)) {
                return ResponseHelper::forbidden('No tienes acceso a esta zona');
            }

            $productId = $priceDetail->product_id;
            $deleted = $this->repository->delete($id);

            if (!$deleted) {
                return ResponseHelper::internalServerError('Error al eliminar el precio');
            }

            $this->clearProductPriceCache($productId);

            return ResponseHelper::success(null, 'Precio eliminado exitosamente');

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al eliminar el precio: ' . $e->getMessage());
        }
    }

    public function forceDeletePriceDetail(int $id): array
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('Admin')) {
                return ResponseHelper::forbidden('No tienes permisos para eliminar permanentemente');
            }

            $deleted = $this->repository->forceDelete($id);

            if (!$deleted) {
                return ResponseHelper::notFound('Precio no encontrado');
            }

            return ResponseHelper::success(null, 'Precio eliminado permanentemente');

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al eliminar permanentemente el precio: ' . $e->getMessage());
        }
    }

    public function getPriceDetailsList(): array
    {
        try {
            $user = Auth::user();
            $allowedZoneIds = $this->getAllowedZoneIds($user);

            $priceDetails = $this->repository->getActiveForDropdown($allowedZoneIds);

            $priceDetailsList = ProductPriceDetailMapper::collectionToDropdownDTOs($priceDetails);

            return ResponseHelper::success(
                $priceDetailsList,
                'Lista de precios obtenida exitosamente'
            );

        } catch (\Exception $e) {
            return ResponseHelper::internalServerError('Error al obtener la lista de precios: ' . $e->getMessage());
        }
    }

    private function getAllowedZoneIds($user): ?array
    {
        if (!$user) {
            return null;
        }

        if ($user->hasRole('Admin')) {
            return null;
        }

        if ($user->hasRole('Vendedor')) {
            return $user->zones()->pluck('zones.id')->toArray();
        }

        return null;
    }

    private function canAccessZone($user, int $zoneId): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Vendedor')) {
            return $user->hasZone($zoneId);
        }

        return true;
    }

    private function canAccessZones($user, array $zoneIds): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Vendedor')) {
            $userZoneIds = $user->zones()->pluck('zones.id')->toArray();
            $unauthorizedZones = array_diff($zoneIds, $userZoneIds);
            return empty($unauthorizedZones);
        }

        return true;
    }

    private function clearProductPriceCache(int $productId): void
    {
        try {
            $zones = Zone::active()->pluck('id');
            
            foreach ($zones as $zoneId) {
                Cache::forget("product_price_{$productId}_zone_{$zoneId}");
            }
            
            Cache::forget("product_price_{$productId}_zone_");
        } catch (\Exception $e) {
        }
    }

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
            
            if (!$zoneId) {
                return [
                    'price' => (float) $product->price,
                    'source' => 'base_price',
                    'source_description' => 'Precio base del producto',
                    'zone_price_detail_id' => null
                ];
            }
            
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
            
            return [
                'price' => (float) $product->price,
                'source' => 'base_price_fallback',
                'source_description' => 'Precio base (no definido para esta zona)',
                'zone_price_detail_id' => null
            ];
        });
    }

    public static function getMultipleProductPricesForZone(array $productIds, ?int $zoneId): array
    {
        $prices = [];
        
        foreach ($productIds as $productId) {
            $prices[$productId] = self::getProductPriceForZone($productId, $zoneId);
        }
        
        return $prices;
    }

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
}