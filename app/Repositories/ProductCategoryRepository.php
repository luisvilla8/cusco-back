<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductCategoryRepository
{
    public function __construct(
        private ProductCategory $model
    ) {}

    public function findActiveWithCount(int $id): ?ProductCategory
    {
        return $this->model->active()
            ->withCount('products')
            ->find($id);
    }

    public function findWithTrashedAndCount(int $id): ?ProductCategory
    {
        return $this->model->withTrashed()
            ->withCount('products')
            ->find($id);
    }

    public function getAllActiveWithPagination(array $filters): LengthAwarePaginator
    {
        $query = $this->model->active();
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->withCount('products')->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->select('id', 'name', 'description')
            ->withCount('products') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->withCount('products') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): ProductCategory
    {
        return DB::transaction(function () use ($data) {
            $productCategory = $this->model->create($data);
            return $this->findActiveWithCount($productCategory->id);
        });
    }

    public function update(int $id, array $data): ?ProductCategory
    {
        return DB::transaction(function () use ($id, $data) {
            $productCategory = $this->findActiveWithCount($id);
            if (!$productCategory) return null;
            
            $productCategory->update($data);
            return $this->findActiveWithCount($id);
        });
    }

    public function softDelete(int $id): ?ProductCategory
    {
        return DB::transaction(function () use ($id) {
            $productCategory = $this->findActiveWithCount($id);
            if (!$productCategory) return null;
            
            $productCategory->delete();
            return $this->findWithTrashedAndCount($id);
        });
    }

    public function forceDelete(int $id): ?ProductCategory
    {
        return DB::transaction(function () use ($id) {
            $productCategory = $this->findWithTrashedAndCount($id);
            if (!$productCategory) return null;
            
            // Create snapshot
            $snapshot = $productCategory->replicate();
            $snapshot->id = $productCategory->id;
            $snapshot->products_count = $productCategory->products_count;
            $snapshot->deleted_at = $productCategory->deleted_at;
            $snapshot->exists = true;
            
            $productCategory->forceDelete();
            return $snapshot;
        });
    }
}