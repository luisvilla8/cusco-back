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
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('symbol', 'like', '%' . $filters['search'] . '%');
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
            ->select('id', 'name', 'symbol')
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
            
            // ✅ CORRECCIÓN: No modificar products_count manualmente
            // Simplemente crear un snapshot simple sin modificar propiedades calculadas
            $snapshot = [
                'id' => $measureType->id,
                'name' => $measureType->name,
                'symbol' => $measureType->symbol,
                'description' => $measureType->description,
                'products_count' => $measureType->products_count, // Solo leer, no modificar
                'deleted_at' => $measureType->deleted_at,
                'created_at' => $measureType->created_at,
                'updated_at' => $measureType->updated_at,
            ];
            
            $measureType->forceDelete();
            
            // ✅ RETORNAR UN OBJETO SIMPLE CON LOS DATOS
            return (object) $snapshot;
        });
    }
}