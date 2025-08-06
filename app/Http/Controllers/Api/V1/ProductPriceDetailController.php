<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Controllers\Api\V1\ProductPriceDetailController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductPriceDetail\{
    IndexProductPriceDetailRequest,
    StoreMassiveProductPriceDetailRequest,
    UpdateProductPriceDetailRequest
};
use App\Services\ProductPriceDetailService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductPriceDetailController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ProductPriceDetailService $service
    ) {}

    /**
     * ✅ OBTENER PRECIOS AGRUPADOS POR PRODUCTO (PRINCIPAL)
     */
    public function index(IndexProductPriceDetailRequest $request): JsonResponse
    {
        $result = $this->service->getAllPriceDetails($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ OBTENER LISTA PLANA DE PRECIOS
     */
    public function flat(IndexProductPriceDetailRequest $request): JsonResponse
    {
        $result = $this->service->getFlatPriceDetailsList($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ OBTENER PRECIO ESPECÍFICO
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->service->getPriceDetail($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ CREAR PRECIOS MASIVOS (SOLO CREAR)
     */
    public function storeMassive(StoreMassiveProductPriceDetailRequest $request): JsonResponse
    {
        $result = $this->service->createMassivePrices($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ACTUALIZAR PRECIOS MASIVOS (SOLO ACTUALIZAR)
     */
    public function updateMassive(StoreMassiveProductPriceDetailRequest $request): JsonResponse
    {
        $result = $this->service->updateMassivePrices($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ OBTENER PRECIOS POR ZONAS DE UN PRODUCTO ESPECÍFICO
     */
    public function getProductZonePrices(int $productId): JsonResponse
    {
        $result = $this->service->getProductZonePrices($productId);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ NUEVO: LIMPIAR TODOS LOS PRECIOS DE UN PRODUCTO
     */
    public function clearProductPrices(int $productId): JsonResponse
    {
        $result = $this->service->clearProductPrices($productId);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ACTUALIZAR PRECIO ESPECÍFICO
     */
    public function update(UpdateProductPriceDetailRequest $request, int $id): JsonResponse
    {
        $result = $this->service->updatePriceDetail($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ELIMINAR PRECIO (SOFT DELETE)
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->service->deletePriceDetail($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ ELIMINAR PRECIO PERMANENTEMENTE
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->service->forceDeletePriceDetail($id);
        return $this->handleServiceResult($result);
    }

    /**
     * ✅ LISTA PARA DROPDOWNS
     */
    public function list(): JsonResponse
    {
        $result = $this->service->getPriceDetailsList();
        return $this->handleServiceResult($result);
    }
}
