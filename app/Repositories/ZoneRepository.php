<?php

namespace App\Repositories;

use App\Models\Zone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ZoneRepository
{
    public function __construct(
        private Zone $model
    ) {}

    public function findActiveWithCount(int $id): ?Zone
    {
        return $this->model->active()
            ->withCount('productPriceDetails')
            ->find($id);
    }

    public function findWithTrashedAndCount(int $id): ?Zone
    {
        return $this->model->withTrashed()
            ->withCount('productPriceDetails')
            ->find($id);
    }

    public function getAllActiveWithPagination(array $filters): LengthAwarePaginator
    {
        $query = $this->model->active();
        
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        // Ordenamiento especial por uso
        if ($sortBy === 'usage') {
            $query->orderByUsage($sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->withCount('productPriceDetails')->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->select('id', 'name', 'description')
            ->withCount('productPriceDetails') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->withCount('productPriceDetails') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getMostUsedZones(int $limit = 10): Collection
    {
        return $this->model->active()
            ->withCount('productPriceDetails')
            ->orderBy('product_price_details_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getZonesWithoutPrices(): Collection
    {
        return $this->model->active()
            ->doesntHave('productPriceDetails')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): Zone
    {
        return DB::transaction(function () use ($data) {
            $zone = $this->model->create($data);
            return $this->findActiveWithCount($zone->id);
        });
    }

    public function update(int $id, array $data): ?Zone
    {
        return DB::transaction(function () use ($id, $data) {
            $zone = $this->findActiveWithCount($id);
            if (!$zone) return null;
            
            $zone->update($data);
            return $this->findActiveWithCount($id);
        });
    }

    public function softDelete(int $id): ?Zone
    {
        return DB::transaction(function () use ($id) {
            $zone = $this->findActiveWithCount($id);
            if (!$zone) return null;
            
            $zone->delete();
            return $this->findWithTrashedAndCount($id);
        });
    }

    public function forceDelete(int $id): ?Zone
    {
        return DB::transaction(function () use ($id) {
            $zone = $this->findWithTrashedAndCount($id);
            if (!$zone) return null;
            
            // Create snapshot
            $snapshot = $zone->replicate();
            $snapshot->id = $zone->id;
            $snapshot->product_price_details_count = $zone->product_price_details_count;
            $snapshot->deleted_at = $zone->deleted_at;
            $snapshot->exists = true;
            
            $zone->forceDelete();
            return $snapshot;
        });
    }
}