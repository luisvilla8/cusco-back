<?php

namespace App\Repositories;

use App\Models\Product;
use App\Traits\LoggingTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ProductRepository
{
    use LoggingTrait;

    public function __construct(
        private Product $model
    ) {}

    /**
     * ✅ CARGAR RELACIONES SIEMPRE
     */
    private function getWithRelations()
    {
        return $this->model->with([
            'measureType:id,name,symbol',
            'productCategory:id,name'
        ]);
    }

    /**
     * Get all active products with pagination
     */
    public function getAllActiveWithPagination(array $filters = []): LengthAwarePaginator
    {
        $query = $this->getWithRelations()
            ->active()
            ->withCount('productPriceDetails');

        // ✅ APLICAR FILTROS
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['product_category_id'])) {
            $query->byCategory($filters['product_category_id']);
        }

        if (!empty($filters['measure_type_id'])) {
            $query->byMeasureType($filters['measure_type_id']);
        }

        if (!empty($filters['stock_status'])) {
            $query->byStockStatus($filters['stock_status']);
        }

        // ✅ ORDENAMIENTO
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc');
        
        if (in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min((int)($filters['per_page'] ?? 15), 100); // Max 100 items per page
        
        return $query->paginate($perPage);
    }

    /**
     * Find active product with relations
     */
    public function findActiveWithRelations(int $id): ?Product
    {
        return $this->getWithRelations()
            ->active()
            ->withCount('productPriceDetails')
            ->find($id);
    }

    /**
     * Find product with trashed and relations
     */
    public function findWithTrashedAndRelations(int $id): ?Product
    {
        return $this->getWithRelations()
            ->withTrashed()
            ->withCount('productPriceDetails')
            ->find($id);
    }

    /**
     * Create new product
     */
    public function create(array $data): Product
    {
        $product = $this->model->create($data);
        
        // ✅ RECARGAR CON RELACIONES
        return $this->findActiveWithRelations($product->id);
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): Product
    {
        // ✅ DEBUG: LOG EN REPOSITORY
        Log::info('=== DEBUG REPOSITORY UPDATE ===', [
            'product_id' => $id,
            'data_to_update' => $data,
            'data_count' => count($data)
        ]);

        $product = $this->model->findOrFail($id);
        
        // ✅ DEBUG: LOG PRODUCTO ANTES DE UPDATE
        Log::info('=== DEBUG BEFORE UPDATE ===', [
            'current_name' => $product->name,
            'current_price' => $product->price,
            'current_updated_at' => $product->updated_at->toDateTimeString()
        ]);

        // ✅ EJECUTAR UPDATE
        $updateResult = $product->update($data);
        
        // ✅ DEBUG: LOG RESULTADO DEL UPDATE
        Log::info('=== DEBUG UPDATE RESULT ===', [
            'update_result' => $updateResult,
            'data_was_changed' => $product->wasChanged(),
            'changed_fields' => $product->getChanges(),
            'new_updated_at' => $product->fresh()->updated_at->toDateTimeString()
        ]);
        
        // ✅ RECARGAR CON RELACIONES
        return $this->findActiveWithRelations($id);
    }

    /**
     * Get products with low stock
     */
    public function getLowStockProducts(): Collection
    {
        return $this->getWithRelations()
            ->active()
            ->whereRaw('stock <= min_stock AND stock > 0')
            ->orderBy('stock', 'asc')
            ->get();
    }

    /**
     * Get products out of stock
     */
    public function getOutOfStockProducts(): Collection
    {
        return $this->getWithRelations()
            ->active()
            ->where('stock', '<=', 0)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId): Collection
    {
        return $this->getWithRelations()
            ->active()
            ->where('product_category_id', $categoryId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active products for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return $this->getWithRelations()
            ->active()
            ->select(['id', 'name', 'code', 'barcode', 'stock', 'measure_type_id'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Update product stock
     */
    public function updateStock(int $id, float $quantity, string $type): ?Product
    {
        $product = $this->model->active()->find($id);
        
        if (!$product) return null;

        switch (strtoupper($type)) {
            case 'IN':
            case 'ADD':
                $product->addStock($quantity);
                break;
            case 'OUT':
            case 'SUBTRACT':
                $product->removeStock($quantity);
                break;
            case 'SET':
                $product->setStock($quantity);
                break;
            default:
                throw new \InvalidArgumentException("Tipo de operación no válido: {$type}");
        }

        // ✅ RECARGAR CON RELACIONES
        return $this->findActiveWithRelations($id);
    }

    /**
     * Soft delete product
     */
    public function softDelete(int $id): Product
    {
        $product = $this->model->findOrFail($id);
        $product->delete();
        
        return $product;
    }

    /**
     * Force delete product
     */
    public function forceDelete(int $id): Product
    {
        $product = $this->model->withTrashed()->findOrFail($id);
        $product->forceDelete();
        
        return $product;
    }
}