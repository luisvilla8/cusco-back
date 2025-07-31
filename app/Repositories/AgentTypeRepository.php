<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\AgentTypeRepository.php

namespace App\Repositories;

use App\Models\AgentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AgentTypeRepository
{
    public function __construct(
        private AgentType $model
    ) {}

    public function findActiveWithCount(int $id): ?AgentType
    {
        return $this->model->active()
            ->withCount('agents')
            ->find($id);
    }

    public function findWithTrashedAndCount(int $id): ?AgentType
    {
        return $this->model->withTrashed()
            ->withCount('agents')
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

        return $query->withCount('agents')->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->select('id', 'name', 'description')
            ->withCount('agents') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->withCount('agents') 
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): AgentType
    {
        return DB::transaction(function () use ($data) {
            $agentType = $this->model->create($data);
            return $this->findActiveWithCount($agentType->id);
        });
    }

    public function update(int $id, array $data): ?AgentType
    {
        return DB::transaction(function () use ($id, $data) {
            $agentType = $this->findActiveWithCount($id);
            if (!$agentType) return null;
            
            $agentType->update($data);
            return $this->findActiveWithCount($id);
        });
    }

    public function softDelete(int $id): ?AgentType
    {
        return DB::transaction(function () use ($id) {
            $agentType = $this->findActiveWithCount($id);
            if (!$agentType) return null;
            
            $agentType->delete();
            return $this->findWithTrashedAndCount($id);
        });
    }

    public function forceDelete(int $id): ?AgentType
    {
        return DB::transaction(function () use ($id) {
            $agentType = $this->findWithTrashedAndCount($id);
            if (!$agentType) return null;
            
            // Create snapshot
            $snapshot = $agentType->replicate();
            $snapshot->id = $agentType->id;
            $snapshot->agents_count = $agentType->agents_count;
            $snapshot->deleted_at = $agentType->deleted_at;
            $snapshot->exists = true;
            
            $agentType->forceDelete();
            return $snapshot;
        });
    }
}