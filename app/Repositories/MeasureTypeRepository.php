<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\MeasureTypeRepository.php

namespace App\Repositories;

use App\Models\MeasureType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MeasureTypeRepository
{
    public function __construct(
        private MeasureType $model
    ) {}

    public function findActiveWithCount(int $id): ?MeasureType
    {
        return $this->model->active()
            ->withCount('products')
            ->find($id);
    }

    public function findWithTrashedAndCount(int $id): ?MeasureType
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
                $q->where('name', 'like', '%' . $filters['search'] . '%')          // ✅ CAMBIO: description → name
                  ->orWhere('symbol', 'like', '%' . $filters['search'] . '%');     // ✅ CAMBIO: acronym → symbol
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';  // ✅ CAMBIO: description → name
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->withCount('products')->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->select('id', 'name', 'symbol')                    // ✅ CAMBIO: description, acronym → name, symbol
            ->withCount('products') 
            ->orderBy('name', 'asc')                            // ✅ CAMBIO: description → name
            ->get();
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->withCount('products') 
            ->orderBy('name', 'asc')                            // ✅ CAMBIO: description → name
            ->get();
    }

    public function create(array $data): MeasureType
    {
        return DB::transaction(function () use ($data) {
            $measureType = $this->model->create($data);
            return $this->findActiveWithCount($measureType->id);
        });
    }

    public function update(int $id, array $data): ?MeasureType
    {
        return DB::transaction(function () use ($id, $data) {
            $measureType = $this->findActiveWithCount($id);
            if (!$measureType) return null;
            
            $measureType->update($data);
            return $this->findActiveWithCount($id);
        });
    }

    public function softDelete(int $id): ?MeasureType
    {
        return DB::transaction(function () use ($id) {
            $measureType = $this->findActiveWithCount($id);
            if (!$measureType) return null;
            
            $measureType->delete();
            return $this->findWithTrashedAndCount($id);
        });
    }

    public function forceDelete(int $id): ?MeasureType
    {
        return DB::transaction(function () use ($id) {
            $measureType = $this->findWithTrashedAndCount($id);
            if (!$measureType) return null;
            
            // Create snapshot
            $snapshot = $measureType->replicate();
            $snapshot->id = $measureType->id;
            $snapshot->products_count = $measureType->products_count;
            $snapshot->deleted_at = $measureType->deleted_at;
            $snapshot->exists = true;
            
            $measureType->forceDelete();
            return $snapshot;
        });
    }
}