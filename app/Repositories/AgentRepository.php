<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\AgentRepository.php

namespace App\Repositories;

use App\Models\Agent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AgentRepository
{
    public function __construct(
        private Agent $model
    ) {}

    public function findActiveWithRelations(int $id): ?Agent
    {
        return $this->model->active()
            ->with('agentType')
            ->find($id);
    }

    public function findWithTrashedAndRelations(int $id): ?Agent
    {
        return $this->model->withTrashed()
            ->with('agentType')
            ->find($id);
    }

    public function getAllActiveWithPagination(array $filters): LengthAwarePaginator
    {
        $query = $this->model->active()->with('agentType');
        
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['agent_type_id'])) {
            $query->byType($filters['agent_type_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->with('agentType')
            ->select('id', 'name', 'dni', 'ruc', 'agent_type_id')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()
            ->with('agentType')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getClients(): Collection
    {
        return $this->model->active()
            ->clients()
            ->with('agentType')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getProviders(): Collection
    {
        return $this->model->active()
            ->providers()
            ->with('agentType')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function getByType(int $agentTypeId): Collection
    {
        return $this->model->active()
            ->byType($agentTypeId)
            ->with('agentType')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function findByDni(string $dni): ?Agent
    {
        return $this->model->active()
            ->where('dni', $dni)
            ->with('agentType')
            ->first();
    }

    public function findByRuc(string $ruc): ?Agent
    {
        return $this->model->active()
            ->where('ruc', $ruc)
            ->with('agentType')
            ->first();
    }

    public function create(array $data): Agent
    {
        return DB::transaction(function () use ($data) {
            $agent = $this->model->create($data);
            return $this->findActiveWithRelations($agent->id);
        });
    }

    public function update(int $id, array $data): ?Agent
    {
        return DB::transaction(function () use ($id, $data) {
            $agent = $this->findActiveWithRelations($id);
            if (!$agent) return null;
            
            $agent->update($data);
            return $this->findActiveWithRelations($id);
        });
    }

    public function softDelete(int $id): ?Agent
    {
        return DB::transaction(function () use ($id) {
            $agent = $this->findActiveWithRelations($id);
            if (!$agent) return null;
            
            $agent->delete();
            return $this->findWithTrashedAndRelations($id);
        });
    }

    public function forceDelete(int $id): ?Agent
    {
        return DB::transaction(function () use ($id) {
            $agent = $this->findWithTrashedAndRelations($id);
            if (!$agent) return null;
            
            // Create snapshot
            $snapshot = $agent->replicate();
            $snapshot->id = $agent->id;
            $snapshot->deleted_at = $agent->deleted_at;
            $snapshot->exists = true;
            $snapshot->setRelation('agentType', $agent->agentType);
            
            $agent->forceDelete();
            return $snapshot;
        });
    }
}