<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ProductCategoryService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\ProductCategoryMapper;
use App\Repositories\ProductCategoryRepository;
use App\Traits\LoggingTrait;

class ProductCategoryService
{
    use LoggingTrait;

    public function __construct(
        private ProductCategoryRepository $productCategoryRepository
    ) {}

    /**
     * Get all product categories with pagination
     */
    public function getAllProductCategories(array $filters = []): array
    {
        $this->logInfo('Fetching product categories with filters', ['filters' => $filters]);

        $paginatedProductCategories = $this->productCategoryRepository->getAllActiveWithPagination($filters);
        $mapped = ProductCategoryMapper::paginatedToDTOs($paginatedProductCategories);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedProductCategories,
            'Categorías de productos obtenidas exitosamente'
        );
    }

    /**
     * Get single product category by ID
     */
    public function getProductCategory(int $id): array
    {
        $this->logInfo('Fetching product category', ['product_category_id' => $id]);

        $productCategory = $this->productCategoryRepository->findActiveWithCount($id);

        if (!$productCategory) {
            return ResponseHelper::notFound('Categoría de producto no encontrada o ha sido eliminada');
        }

        $productCategoryDTO = ProductCategoryMapper::modelToDTO($productCategory);
        return ResponseHelper::success($productCategoryDTO, 'Categoría de producto obtenida exitosamente');
    }

    /**
     * Create new product category
     */
    public function createProductCategory(array $data): array
    {
        $this->logInfo('Creating new product category', ['data' => $data]);

        $productCategory = $this->productCategoryRepository->create($data);
        $productCategoryDTO = ProductCategoryMapper::modelToDTO($productCategory);

        $this->logInfo('Product category created successfully', ['product_category_id' => $productCategory->id]);

        return ResponseHelper::created($productCategoryDTO, 'Categoría de producto creada exitosamente');
    }

    /**
     * Update existing product category
     */
    public function updateProductCategory(int $id, array $data): array
    {
        $this->logInfo('Updating product category', ['product_category_id' => $id, 'data' => $data]);

        $productCategory = $this->productCategoryRepository->update($id, $data);

        if (!$productCategory) {
            return ResponseHelper::notFound('Categoría de producto no encontrada o ha sido eliminada');
        }

        $productCategoryDTO = ProductCategoryMapper::modelToDTO($productCategory);

        $this->logInfo('Product category updated successfully', ['product_category_id' => $id]);

        return ResponseHelper::success($productCategoryDTO, 'Categoría de producto actualizada exitosamente');
    }

    /**
     * Soft delete product category
     */
    public function deleteProductCategory(int $id): array
    {
        $this->logInfo('Attempting to delete product category', ['product_category_id' => $id]);

        $productCategory = $this->productCategoryRepository->findActiveWithCount($id);

        if (!$productCategory) {
            return ResponseHelper::notFound('Categoría de producto no encontrada o ha sido eliminada');
        }

        $deletedProductCategory = $this->productCategoryRepository->softDelete($id);
        $deletedProductCategoryDTO = ProductCategoryMapper::modelToDeletedDTO($deletedProductCategory);

        $this->logInfo('Product category deleted successfully', ['product_category_id' => $id]);

        return ResponseHelper::success($deletedProductCategoryDTO, 'Categoría de producto eliminada exitosamente');
    }

    /**
     * Force delete product category
     */
    public function forceDeleteProductCategory(int $id): array
    {
        $this->logInfo('Attempting to force delete product category', ['product_category_id' => $id]);

        $productCategory = $this->productCategoryRepository->findWithTrashedAndCount($id);

        if (!$productCategory) {
            return ResponseHelper::notFound('Categoría de producto no encontrada');
        }

        $productCategoryName = $productCategory->name;

        $deletedProductCategory = $this->productCategoryRepository->forceDelete($id);
        $deletedProductCategoryDTO = ProductCategoryMapper::modelToDeletedDTO($deletedProductCategory);

        $this->logWarning('Product category force deleted', [
            'product_category_id' => $id,
            'product_category_name' => $productCategoryName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedProductCategoryDTO, "Categoría '{$productCategoryName}' eliminada permanentemente");
    }

    /**
     * Get product categories list for dropdown
     */
    public function getProductCategoriesList(): array
    {
        $this->logInfo('Fetching product categories list for dropdown');

        $productCategories = $this->productCategoryRepository->getActiveForDropdown();
        $dropdownData = ProductCategoryMapper::collectionToDropdownDTOs($productCategories);

        return ResponseHelper::success($dropdownData, 'Lista de categorías de productos obtenida exitosamente');
    }
}