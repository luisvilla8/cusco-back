<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\{StoreProductRequest, UpdateProductRequest, IndexProductRequest, StockUpdateRequest};
use App\Services\ProductService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ProductService $productService
    ) {}

    /**
     * Get all products with pagination
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        $result = $this->productService->getAllProducts($request->validated());
        return $this->handleServiceResult($result);
    }

    /**
     * Get single product
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->productService->getProduct($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Create new product
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $result = $this->productService->createProduct($request->validated());
        return $this->handleServiceResult($result);
    }


    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        Log::info('=== UPDATE WITH CUSTOM REQUEST ===', [
            'product_id' => $id,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all(),
        ]);

        try {
            $validated = $request->validated();
            
            Log::info('Custom request validation passed', ['validated_data' => $validated]);

            $result = $this->productService->updateProduct($id, $validated);
            return $this->handleServiceResult($result);

        } catch (\Exception $e) {
            Log::error('Update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete product
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->productService->deleteProduct($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Force delete product
     */
    public function forceDelete(int $id): JsonResponse
    {
        $result = $this->productService->forceDeleteProduct($id);
        return $this->handleServiceResult($result);
    }

    /**
     * Get products list for dropdown
     */
    public function list(): JsonResponse
    {
        $result = $this->productService->getProductsList();
        return $this->handleServiceResult($result);
    }

    /**
     * Get products with low stock
     */
    public function lowStock(): JsonResponse
    {
        $result = $this->productService->getLowStockProducts();
        return $this->handleServiceResult($result);
    }

    /**
     * Get products out of stock
     */
    public function outOfStock(): JsonResponse
    {
        $result = $this->productService->getOutOfStockProducts();
        return $this->handleServiceResult($result);
    }

    /**
     * Get products by category
     */
    public function byCategory(int $categoryId): JsonResponse
    {
        $result = $this->productService->getProductsByCategory($categoryId);
        return $this->handleServiceResult($result);
    }

    /**
     * Add stock to product
     */
    public function addStock(StockUpdateRequest $request, int $id): JsonResponse
    {
        $result = $this->productService->updateStock($id, $request->validated()['quantity'], 'IN');
        return $this->handleServiceResult($result);
    }

    /**
     * Remove stock from product
     */
    public function removeStock(StockUpdateRequest $request, int $id): JsonResponse
    {
        $result = $this->productService->updateStock($id, $request->validated()['quantity'], 'OUT');
        return $this->handleServiceResult($result);
    }

    /**
     * Set product stock
     */
    public function setStock(StockUpdateRequest $request, int $id): JsonResponse
    {
        $result = $this->productService->updateStock($id, $request->validated()['quantity'], 'SET');
        return $this->handleServiceResult($result);
    }
}
