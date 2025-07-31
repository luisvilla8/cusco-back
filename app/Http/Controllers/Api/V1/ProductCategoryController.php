<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductCategory\{StoreProductCategoryRequest, UpdateProductCategoryRequest, IndexProductCategoryRequest};
use App\Services\ProductCategoryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ProductCategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ProductCategoryService $productCategoryService
    ) {}

    /**
     * Get all product categories with pagination
     */
    public function index(IndexProductCategoryRequest $request): JsonResponse
    {
        $result = $this->productCategoryService->getAllProductCategories($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single product category
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->productCategoryService->getProductCategory($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new product category
     */
    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $result = $this->productCategoryService->createProductCategory($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Update existing product category
     */
    public function update(UpdateProductCategoryRequest $request, int $id): JsonResponse
    {
        $result = $this->productCategoryService->updateProductCategory($id, $request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Soft delete product category
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->productCategoryService->deleteProductCategory($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete product category
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->productCategoryService->forceDeleteProductCategory($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get product categories list for dropdown
     */
    public function list(): JsonResponse
    {
        $result = $this->productCategoryService->getProductCategoriesList();
        return $this->handleServiceResult($result);
    }
}
