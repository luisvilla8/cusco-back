<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\TripRepository.php

namespace App\Repositories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TripRepository
{
    public function __construct(
        private Trip $model
    ) {}

    public function findActiveWithRelations(int $id): ?Trip
    {
        return $this->model->active()
            ->withRelations()
            ->withFullStats()
            ->find($id);
    }

    public function findWithTrashedAndRelations(int $id): ?Trip
    {
        return $this->model->withTrashed()
            ->withRelations()
            ->withFullStats()
            ->find($id);
    }

    /**
     * ✅ OBTENER TRIPS CON FILTROS Y PERMISOS
     */
    public function getAllActiveWithPagination(array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->model->active()->withRelations();

        // ✅ FILTRAR POR PERMISOS: Vendedores solo ven sus trips
        if (!$user->hasAnyRole(['Administrador', 'Super Admin'])) {
            $query->byUser($user->id);
        }

        // ✅ APLICAR FILTROS
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['agent_id'])) {
            $query->byAgent($filters['agent_id']);
        }

        if (!empty($filters['zone_id'])) {
            $query->byZone($filters['zone_id']);
        }

        // Solo admins pueden filtrar por otro usuario
        if (!empty($filters['user_id']) && $user->hasAnyRole(['Administrador', 'Super Admin'])) {
            $query->byUser($filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        if (!empty($filters['date_start'])) {
            $query->whereDate('date_start', '>=', $filters['date_start']);
        }

        if (!empty($filters['date_end'])) {
            $query->whereDate('date_end', '<=', $filters['date_end']);
        }

        // ✅ ORDENAMIENTO
        $sortBy = $filters['sort_by'] ?? 'date_start';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        if ($sortBy === 'duration') {
            $query->orderByDuration($sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->withFullStats()->paginate($filters['per_page'] ?? 15);
    }

    /**
     * ✅ APLICAR FILTRO DE ESTADO
     */
    private function applyStatusFilter($query, string $status): void
    {
        $today = now()->toDateString();
        
        match($status) {
            'upcoming' => $query->where('date_start', '>', $today),
            'in_progress' => $query->where('date_start', '<=', $today)->where('date_end', '>=', $today),
            'completed' => $query->where('date_end', '<', $today),
        };
    }

    /**
     * ✅ PARA DROPDOWNS (con permisos)
     */
    public function getActiveForDropdown(User $user): Collection
    {
        $query = $this->model->active()
            ->select('id', 'name', 'code', 'date_start', 'date_end')
            ->orderBy('date_start', 'desc');

        // Vendedores solo ven sus trips
        if (!$user->hasAnyRole(['Administrador', 'Super Admin'])) {
            $query->byUser($user->id);
        }

        return $query->get();
    }

    public function create(array $data): Trip
    {
        return DB::transaction(function () use ($data) {
            $trip = $this->model->create($data);
            return $this->findActiveWithRelations($trip->id);
        });
    }

    public function update(int $id, array $data, User $user): ?Trip
    {
        return DB::transaction(function () use ($id, $data, $user) {
            $trip = $this->findActiveWithRelations($id);
            if (!$trip || !$trip->canUserEdit($user)) {
                return null;
            }
            
            $trip->update($data);
            return $this->findActiveWithRelations($id);
        });
    }

    public function softDelete(int $id, User $user): ?Trip
    {
        return DB::transaction(function () use ($id, $user) {
            $trip = $this->findActiveWithRelations($id);
            if (!$trip || !$trip->canUserEdit($user)) {
                return null;
            }
            
            $trip->delete();
            return $this->findWithTrashedAndRelations($id);
        });
    }

    public function forceDelete(int $id, User $user): ?Trip
    {
        return DB::transaction(function () use ($id, $user) {
            $trip = $this->findWithTrashedAndRelations($id);
            if (!$trip || !$trip->canUserEdit($user)) {
                return null;
            }
            
            // ✅ LOGGING DETALLADO
            \Log::info("Starting force delete process", [
                'trip_id' => $id,
                'user_id' => $user->id
            ]);
            
            // Create snapshot ANTES de eliminar
            $snapshot = $trip->replicate();
            $snapshot->id = $trip->id;
            $snapshot->deleted_at = $trip->deleted_at;
            $snapshot->exists = true;
            
            try {
                // ✅ FORZAR ELIMINACIÓN SIN EVENTOS (para evitar problemas)
                $trip->forceDelete();
                
                \Log::info("Trip force deleted successfully", [
                    'trip_id' => $id
                ]);
                
                return $snapshot;
                
            } catch (\Exception $e) {
                \Log::error("Error in force delete", [
                    'trip_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * ✅ VERIFICAR ACCESO A TRIP
     */
    public function canUserAccessTrip(int $tripId, User $user): bool
    {
        $trip = $this->model->find($tripId);
        return $trip && $trip->canUserAccess($user);
    }
}