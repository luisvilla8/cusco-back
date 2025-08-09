<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\ProductService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\ProductMapper;
use App\Repositories\ProductRepository;
use App\Services\ImageService;
use App\Traits\LoggingTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class ProductService
{
    use LoggingTrait;

    public function __construct(
        private ProductRepository $productRepository,
        private ImageService $imageService
    ) {}

    /**
     * Get all products with pagination
     */
    public function getAllProducts(array $filters = []): array
    {
        $this->logInfo('Fetching products with filters', ['filters' => $filters]);

        $paginatedProducts = $this->productRepository->getAllActiveWithPagination($filters);
        $mapped = ProductMapper::paginatedToDTOs($paginatedProducts);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedProducts,
            'Productos obtenidos exitosamente'
        );
    }

    /**
     * Get single product by ID
     */
    public function getProduct(int $id): array
    {
        $this->logInfo('Fetching product', ['product_id' => $id]);

        $product = $this->productRepository->findActiveWithRelations($id);

        if (!$product) {
            return ResponseHelper::notFound('Producto no encontrado o ha sido eliminado');
        }

        $productDTO = ProductMapper::modelToDTO($product);
        return ResponseHelper::success($productDTO, 'Producto obtenido exitosamente');
    }

    /**
     * Create new product
     */
    public function createProduct(array $data): array
    {
        $this->logInfo('Creating new product', ['data' => Arr::except($data, ['image'])]);

        //  PROCESAR IMAGEN SI EXISTE - CON MEJOR MANEJO DE ERRORES
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            try {
                $this->logInfo('Processing product image', ['has_image' => true]);
                $imageResult = $this->imageService->storeProductImage($data['image'], $data['code'] ?? null);
                $data['image_url'] = $imageResult['resized_url'];
                $this->logInfo('Image processed successfully', ['image_url' => $data['image_url']]);
            } catch (\Exception $e) {
                $this->logError('Failed to process image - continuing without image', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continuar sin imagen si hay error
                $data['image_url'] = null;
            }
            unset($data['image']); // Remover el archivo del array
        }

        //  VALIDAR QUE TODOS LOS CAMPOS ESTÉN PRESENTES
        $this->logInfo('Data before create', $data);

        $product = $this->productRepository->create($data);
        
        //  LOG DESPUÉS DE CREAR
        $this->logInfo('Product created in database', [
            'product_id' => $product->id,
            'image_url_saved' => $product->image_url,
            'cost_saved' => $product->cost,
            'price_saved' => $product->price
        ]);

        $productDTO = ProductMapper::modelToDTO($product);

        $this->logInfo('Product created successfully', [
            'product_id' => $product->id,
            'has_image' => !empty($product->image_url)
        ]);

        return ResponseHelper::created($productDTO, 'Producto creado exitosamente');
    }

    /**
     * Update existing product
     */
    public function updateProduct(int $id, array $data): array
    {
        //  CAMBIAR array_except() POR Arr::except()
        $this->logInfo('Updating product', ['product_id' => $id, 'data' => Arr::except($data, ['image'])]);

        $product = $this->productRepository->findActiveWithRelations($id);

        if (!$product) {
            return ResponseHelper::notFound('Producto no encontrado o ha sido eliminado');
        }

        //  PROCESAR IMAGEN SI EXISTE
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            try {
                // Eliminar imagen anterior si existe
                if ($product->image_url) {
                    $this->imageService->deleteProductImage($product->image_url);
                }
                
                // Subir nueva imagen
                $imageResult = $this->imageService->storeProductImage($data['image'], $product->code);
                $data['image_url'] = $imageResult['resized_url'];
            } catch (\Exception $e) {
                $this->logError('Failed to process image', ['error' => $e->getMessage()]);
                // Continuar sin cambiar imagen si hay error
            }
            unset($data['image']);
        }

        //  ELIMINAR IMAGEN SI SE SOLICITA
        if (isset($data['remove_image']) && $data['remove_image'] && $product->image_url) {
            try {
                $this->imageService->deleteProductImage($product->image_url);
                $data['image_url'] = null;
            } catch (\Exception $e) {
                $this->logError('Failed to delete image', ['error' => $e->getMessage()]);
            }
            unset($data['remove_image']);
        }

        $this->logInfo('Data before update', $data);

        $updatedProduct = $this->productRepository->update($id, $data);
        $productDTO = ProductMapper::modelToDTO($updatedProduct);

        $this->logInfo('Product updated successfully', ['product_id' => $id]);

        return ResponseHelper::success($productDTO, 'Producto actualizado exitosamente');
    }

    /**
     * Update product stock
     */
    public function updateStock(int $id, float $quantity, string $type): array
    {
        $this->logInfo('Updating product stock', [
            'product_id' => $id,
            'quantity' => $quantity,
            'type' => $type
        ]);

        $product = $this->productRepository->updateStock($id, $quantity, $type);

        if (!$product) {
            return ResponseHelper::notFound('Producto no encontrado o ha sido eliminado');
        }

        $productDTO = ProductMapper::modelToDTO($product);

        $this->logInfo('Product stock updated successfully', [
            'product_id' => $id,
            'new_stock' => $product->stock
        ]);

        return ResponseHelper::success($productDTO, 'Stock actualizado exitosamente');
    }

    /**
     * Get products with low stock
     */
    public function getLowStockProducts(): array
    {
        $this->logInfo('Fetching low stock products');

        $products = $this->productRepository->getLowStockProducts();
        $productsData = ProductMapper::collectionToArrays($products);

        return ResponseHelper::success($productsData, 'Productos con stock bajo obtenidos exitosamente');
    }

    /**
     * Get products out of stock
     */
    public function getOutOfStockProducts(): array
    {
        $this->logInfo('Fetching out of stock products');

        $products = $this->productRepository->getOutOfStockProducts();
        $productsData = ProductMapper::collectionToArrays($products);

        return ResponseHelper::success($productsData, 'Productos sin stock obtenidos exitosamente');
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory(int $categoryId): array
    {
        $this->logInfo('Fetching products by category', ['category_id' => $categoryId]);

        $products = $this->productRepository->getByCategory($categoryId);
        $productsData = ProductMapper::collectionToArrays($products);

        return ResponseHelper::success($productsData, 'Productos obtenidos exitosamente');
    }

    /**
     * Soft delete product
     */
    public function deleteProduct(int $id): array
    {
        $this->logInfo('Attempting to delete product', ['product_id' => $id]);

        $product = $this->productRepository->findActiveWithRelations($id);

        if (!$product) {
            return ResponseHelper::notFound('Producto no encontrado o ha sido eliminado');
        }

        $deletedProduct = $this->productRepository->softDelete($id);
        $deletedProductDTO = ProductMapper::modelToDeletedDTO($deletedProduct);

        $this->logInfo('Product deleted successfully', ['product_id' => $id]);

        return ResponseHelper::success($deletedProductDTO, 'Producto eliminado exitosamente');
    }

    /**
     * Force delete product
     */
    public function forceDeleteProduct(int $id): array
    {
        $this->logInfo('Attempting to force delete product', ['product_id' => $id]);

        $product = $this->productRepository->findWithTrashedAndRelations($id);

        if (!$product) {
            return ResponseHelper::notFound('Producto no encontrado');
        }

        $productName = $product->name;

        //  ELIMINAR IMAGEN SI EXISTE
        if ($product->image_url) {
            try {
                $this->imageService->deleteProductImage($product->image_url);
            } catch (\Exception $e) {
                $this->logError('Failed to delete image during force delete', ['error' => $e->getMessage()]);
            }
        }

        $deletedProduct = $this->productRepository->forceDelete($id);
        $deletedProductDTO = ProductMapper::modelToDeletedDTO($deletedProduct);

        $this->logWarning('Product force deleted', [
            'product_id' => $id,
            'product_name' => $productName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedProductDTO, "Producto '{$productName}' eliminado permanentemente");
    }

    /**
     * Get products list for dropdown
     */
    public function getProductsList(): array
    {
        $this->logInfo('Fetching products list for dropdown');

        $products = $this->productRepository->getActiveForDropdown();
        $dropdownData = ProductMapper::collectionToDropdownDTOs($products);

        return ResponseHelper::success($dropdownData, 'Lista de productos obtenida exitosamente');
    }
}