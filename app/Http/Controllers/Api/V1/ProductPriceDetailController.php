<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Api\V1\ProductPriceDetail\ProductPriceDetailResource;
use App\Http\Requests\Api\V1\ProductPriceDetail\StoreProductPriceDetailRequest;
use App\Http\Requests\Api\V1\ProductPriceDetail\UpdateProductPriceDetailRequest;
use App\Services\ProductPriceDetailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductPriceDetailController extends BaseController
{
    public function __construct(
        private ProductPriceDetailService $productPriceDetailService
    ) {}

    /**
     * Get all prices by zones for a specific product
     */
    public function getProductZonePrices(int $productId): JsonResponse
    {
        $result = $this->productPriceDetailService->getProductPricesForAllZones($productId);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Set price for a product in a specific zone
     */
    public function setZonePrice(Request $request, int $productId, int $zoneId): JsonResponse
    {
        $request->validate([
            'price' => 'required|numeric|min:0'
        ], [
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
        ]);

        $result = $this->productPriceDetailService->setProductZonePrice(
            $productId, 
            $zoneId, 
            $request->input('price')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse(
            new ProductPriceDetailResource($result['data']),
            $result['message']
        );
    }

    /**
     * Remove custom price (revert to base price)
     */
    public function removeZonePrice(int $productId, int $zoneId): JsonResponse
    {
        $result = $this->productPriceDetailService->removeProductZonePrice($productId, $zoneId);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse([], $result['message']);
    }

    /**
     * Set prices for a product in multiple zones (bulk)
     */
    public function setMultipleZonePrices(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'zone_prices' => 'required|array|min:1',
            'zone_prices.*.zone_id' => 'required|integer|exists:zones,id',
            'zone_prices.*.price' => 'required|numeric|min:0'
        ], [
            'zone_prices.required' => 'Los precios por zona son obligatorios.',
            'zone_prices.array' => 'Los precios deben ser un array.',
            'zone_prices.min' => 'Debe proporcionar al menos un precio.',
            'zone_prices.*.zone_id.required' => 'El ID de la zona es obligatorio.',
            'zone_prices.*.zone_id.exists' => 'La zona no existe.',
            'zone_prices.*.price.required' => 'El precio es obligatorio.',
            'zone_prices.*.price.numeric' => 'El precio debe ser un número.',
            'zone_prices.*.price.min' => 'El precio no puede ser negativo.',
        ]);

        $result = $this->productPriceDetailService->setMultipleZonePrices(
            $productId, 
            $request->input('zone_prices')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Apply formula to create prices for a product in multiple zones
     */
    public function applyPriceFormula(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:zones,id',
            'formula_type' => 'required|in:percentage,fixed_amount,fixed_price',
            'formula_value' => 'required|numeric',
        ], [
            'zone_ids.required' => 'Las zonas son obligatorias.',
            'zone_ids.array' => 'Las zonas deben ser un array.',
            'zone_ids.min' => 'Debe seleccionar al menos una zona.',
            'zone_ids.*.exists' => 'Una de las zonas no existe.',
            'formula_type.required' => 'El tipo de fórmula es obligatorio.',
            'formula_type.in' => 'El tipo de fórmula debe ser: percentage (porcentaje), fixed_amount (cantidad fija), o fixed_price (precio fijo).',
            'formula_value.required' => 'El valor de la fórmula es obligatorio.',
            'formula_value.numeric' => 'El valor de la fórmula debe ser un número.',
        ]);

        $result = $this->productPriceDetailService->applyFormulaToProduct(
            $productId,
            $request->input('zone_ids'),
            $request->input('formula_type'),
            $request->input('formula_value')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Get all zone prices for a product (including zones without custom prices)
     */
    public function getAllZonePrices(int $productId): JsonResponse
    {
        $result = $this->productPriceDetailService->getAllZonePricesDetailed($productId);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Update prices for multiple zones at once
     */
    public function updateMultipleZonePrices(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'zone_prices' => 'required|array|min:1',
            'zone_prices.*.zone_id' => 'required|integer|exists:zones,id',
            'zone_prices.*.price' => 'required|numeric|min:0'
        ], [
            'zone_prices.required' => 'Los precios por zona son obligatorios.',
            'zone_prices.array' => 'Los precios deben ser un array.',
            'zone_prices.min' => 'Debe proporcionar al menos un precio.',
            'zone_prices.*.zone_id.required' => 'El ID de la zona es obligatorio.',
            'zone_prices.*.zone_id.exists' => 'La zona no existe.',
            'zone_prices.*.price.required' => 'El precio es obligatorio.',
            'zone_prices.*.price.numeric' => 'El precio debe ser un número.',
            'zone_prices.*.price.min' => 'El precio no puede ser negativo.',
        ]);

        $result = $this->productPriceDetailService->updateMultipleZonePrices(
            $productId, 
            $request->input('zone_prices')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Soft delete prices for multiple zones
     */
    public function softDeleteMultipleZonePrices(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:zones,id',
        ], [
            'zone_ids.required' => 'Las zonas son obligatorias.',
            'zone_ids.array' => 'Las zonas deben ser un array.',
            'zone_ids.min' => 'Debe seleccionar al menos una zona.',
            'zone_ids.*.exists' => 'Una de las zonas no existe.',
        ]);

        $result = $this->productPriceDetailService->softDeleteMultipleZonePrices(
            $productId,
            $request->input('zone_ids')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Hard delete (remove custom prices) for multiple zones - revert to base price
     */
    public function removeMultipleZonePrices(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'zone_ids' => 'required|array|min:1',
            'zone_ids.*' => 'integer|exists:zones,id',
        ], [
            'zone_ids.required' => 'Las zonas son obligatorias.',
            'zone_ids.array' => 'Las zonas deben ser un array.',
            'zone_ids.min' => 'Debe seleccionar al menos una zona.',
            'zone_ids.*.exists' => 'Una de las zonas no existe.',
        ]);

        $result = $this->productPriceDetailService->removeMultipleZonePrices(
            $productId,
            $request->input('zone_ids')
        );

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Get summary of all zone prices (active, trashed, base price)
     */
    public function getZonePricesSummary(int $productId): JsonResponse
    {
        $result = $this->productPriceDetailService->getZonePricesSummary($productId);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Get all products with their zone prices (PIVOT style)
     */
    public function getAllProductsWithZonePrices(Request $request): JsonResponse
    {
        $filters = [
            'include_deleted' => $request->boolean('include_deleted', false),
            'only_with_custom_prices' => $request->boolean('only_with_custom_prices', false),
            'zone_ids' => $request->input('zone_ids', []),
            'product_ids' => $request->input('product_ids', []),
            'category_ids' => $request->input('category_ids', []),
        ];

        $result = $this->productPriceDetailService->getAllProductsWithZonePrices($filters);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }

    /**
     * Get products with zone prices in simplified PIVOT format
     */
    public function getProductsZonePricesPivot(Request $request): JsonResponse
    {
        $filters = [
            'include_deleted' => $request->boolean('include_deleted', false),
            'only_with_custom_prices' => $request->boolean('only_with_custom_prices', false),
            'zone_ids' => $request->input('zone_ids', []),
            'product_ids' => $request->input('product_ids', []),
            'category_ids' => $request->input('category_ids', []),
        ];

        $result = $this->productPriceDetailService->getProductsZonePricesPivot($filters);

        if (!$result['success']) {
            return $this->sendError($result['message'], [], $result['code']);
        }

        return $this->sendResponse($result['data'], $result['message']);
    }
}