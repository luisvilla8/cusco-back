<?php

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleRepository
{
    public function __construct(
        private Role $model
    ) {}

    public function findActiveWithCount(int $id): ?Role
    {
        return $this->model->active()
            ->withCount('users')
            ->find($id);
    }

    public function findWithTrashedAndCount(int $id): ?Role
    {
        return $this->model->withTrashed()
            ->withCount('users')
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

        return $query->withCount('users')->paginate($filters['per_page'] ?? 15);
    }

  
    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->select('id', 'name', 'description')
            ->withCount('users') 
            ->orderBy('name', 'asc')
            ->get();
    }


    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->withCount('users') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = $this->model->create($data);
            // ✅ RECARGAR: Con count para response consistente
            return $this->findActiveWithCount($role->id);
        });
    }

    public function update(int $id, array $data): ?Role
    {
        return DB::transaction(function () use ($id, $data) {
            $role = $this->findActiveWithCount($id);
            if (!$role) return null;
            
            $role->update($data);
            // ✅ RECARGAR: Con count actualizado
            return $this->findActiveWithCount($id);
        });
    }

    public function softDelete(int $id): ?Role
    {
        return DB::transaction(function () use ($id) {
            $role = $this->findActiveWithCount($id);
            if (!$role) return null;
            
            $role->delete();
            return $this->findWithTrashedAndCount($id);
        });
    }

    public function forceDelete(int $id): ?Role
    {
        return DB::transaction(function () use ($id) {
            $role = $this->findWithTrashedAndCount($id);
            if (!$role) return null;
            
            // Create snapshot
            $snapshot = $role->replicate();
            $snapshot->id = $role->id;
            $snapshot->users_count = $role->users_count;
            $snapshot->deleted_at = $role->deleted_at;
            $snapshot->exists = true;
            
            $role->forceDelete();
            return $snapshot;
        });
    }
}