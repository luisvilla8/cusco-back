<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\UserRepository.php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserZone;
use App\Repositories\AuthRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    public function __construct(
        private User $model,
        private AuthRepository $authRepository
    ) {}

    public function findActive(int $id): ?User
    {
        return $this->model->active()
            ->with(['role', 'zones'])
            ->find($id);
    }

    public function getAllActiveWithPagination(array $filters): LengthAwarePaginator
    {
        $query = $this->model->active()->with(['role', 'zones']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (!empty($filters['zone_id'])) {
            $query->whereHas('zones', function ($zoneQuery) use ($filters) {
                $zoneQuery->where('zone_id', $filters['zone_id']);
            });
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getActiveForDropdown(): Collection
    {
        return $this->model->active()
            ->with('role')
            ->select('id', 'code', 'name', 'role_id')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function create(array $data): User
    {
        return $this->authRepository->createUserFromAdmin($data);
    }

    public function update(int $id, array $data): ?User
    {
        $user = $this->findActive($id);
        if (!$user) {
            return null;
        }

        return DB::transaction(function () use ($user, $data) {
            Log::info('Updating user', [
                'user_id' => $user->id,
                'data_keys' => array_keys($data),
                'zone_ids_present' => array_key_exists('zone_ids', $data),
                'zone_ids_value' => $data['zone_ids'] ?? 'not_present'
            ]);

            // ✅ EXTRAER zone_ids ANTES de actualizar usuario
            $shouldUpdateZones = array_key_exists('zone_ids', $data);
            $zoneIds = $data['zone_ids'] ?? null;
            unset($data['zone_ids']);

            // Hash password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Actualizar datos del usuario
            if (!empty($data)) {
                $user->update($data);
            }
            
            // ✅ GESTIÓN INTELIGENTE DE ZONAS CON SOFT DELETES
            if ($shouldUpdateZones) {
                $this->updateUserZonesIntelligently($user->id, $zoneIds ?? []);
            }

            // Recargar el usuario con relaciones
            return $this->findActive($user->id);
        });
    }

    /**
     * ✅ MÉTODO INTELIGENTE PARA MANEJAR ZONAS CON SOFT DELETES
     */
    private function updateUserZonesIntelligently(int $userId, array $newZoneIds): void
    {
        Log::info('Updating user zones intelligently', [
            'user_id' => $userId,
            'new_zone_ids' => $newZoneIds
        ]);

        // ✅ 1. OBTENER todas las asignaciones (incluidas las eliminadas)
        $allUserZones = UserZone::withTrashed()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('zone_id');

        Log::info('Current user zones (including soft deleted)', [
            'user_id' => $userId,
            'all_zones' => $allUserZones->mapWithKeys(function($uz) {
                return [$uz->zone_id => [
                    'id' => $uz->id,
                    'deleted_at' => $uz->deleted_at?->format('Y-m-d H:i:s')
                ]];
            })->toArray()
        ]);

        // ✅ 2. PROCESAR cada zona nueva
        foreach ($newZoneIds as $zoneId) {
            $existingAssignment = $allUserZones->get($zoneId);
            
            if ($existingAssignment) {
                // ✅ EXISTE: Si está eliminada, restaurarla
                if ($existingAssignment->deleted_at) {
                    $existingAssignment->restore();
                    Log::info('Restored zone assignment', [
                        'user_id' => $userId,
                        'zone_id' => $zoneId,
                        'assignment_id' => $existingAssignment->id
                    ]);
                } else {
                    // ✅ YA ESTÁ ACTIVA: No hacer nada
                    Log::info('Zone assignment already active', [
                        'user_id' => $userId,
                        'zone_id' => $zoneId,
                        'assignment_id' => $existingAssignment->id
                    ]);
                }
            } else {
                // ✅ NO EXISTE: Crear nueva asignación
                $newAssignment = UserZone::create([
                    'user_id' => $userId,
                    'zone_id' => $zoneId
                ]);
                Log::info('Created new zone assignment', [
                    'user_id' => $userId,
                    'zone_id' => $zoneId,
                    'assignment_id' => $newAssignment->id
                ]);
            }
        }

        // ✅ 3. SOFT DELETE zonas que ya no están en la nueva lista
        $currentActiveZones = UserZone::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotIn('zone_id', $newZoneIds)
            ->get();

        foreach ($currentActiveZones as $zoneToRemove) {
            $zoneToRemove->delete(); // Soft delete
            Log::info('Soft deleted zone assignment', [
                'user_id' => $userId,
                'zone_id' => $zoneToRemove->zone_id,
                'assignment_id' => $zoneToRemove->id
            ]);
        }

        // ✅ 4. LOG FINAL STATE
        $finalActiveZones = UserZone::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('zone_id')
            ->toArray();

        Log::info('Final user zone state', [
            'user_id' => $userId,
            'active_zone_ids' => $finalActiveZones
        ]);
    }

    /**
     * ✅ SOFT DELETE MEJORADO
     */
    public function delete(int $id): bool
    {
        $user = $this->findActive($id);
        if (!$user) {
            Log::warning('Attempted to delete non-existent user', ['user_id' => $id]);
            return false;
        }

        return DB::transaction(function () use ($user) {
            Log::info('Starting user soft delete', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'current_zones_count' => $user->zones()->count()
            ]);

            // ✅ El modelo User maneja automáticamente UserZones en el evento deleting
            $result = $user->delete();

            if ($result) {
                Log::info('User soft deleted successfully', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
            } else {
                Log::error('Failed to soft delete user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
            }

            return $result;
        });
    }

    /**
     * ✅ FORCE DELETE MEJORADO
     */
    public function forceDelete(int $id): bool
    {
        // ✅ Buscar usuario incluyendo soft deleted
        $user = $this->model->withTrashed()->find($id);
        if (!$user) {
            Log::warning('Attempted to force delete non-existent user', ['user_id' => $id]);
            return false;
        }

        return DB::transaction(function () use ($user) {
            Log::info('Starting user force delete', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'is_soft_deleted' => !is_null($user->deleted_at),
                'total_zones_count' => UserZone::withTrashed()->where('user_id', $user->id)->count()
            ]);

            // ✅ Marcar que está siendo force deleted para el evento
            $user->forceDeleting = true;

            // ✅ El modelo User maneja automáticamente UserZones en el evento forceDeleting
            $result = $user->forceDelete();

            if ($result) {
                Log::info('User force deleted successfully', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
            } else {
                Log::error('Failed to force delete user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
            }

            return $result;
        });
    }

    /**
     * ✅ NUEVO: RESTAURAR USUARIO
     */
    public function restore(int $id): ?User
    {
        $user = $this->model->onlyTrashed()->find($id);
        if (!$user) {
            Log::warning('Attempted to restore non-existent soft deleted user', ['user_id' => $id]);
            return null;
        }

        return DB::transaction(function () use ($user) {
            Log::info('Starting user restore', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'deleted_at' => $user->deleted_at?->format('Y-m-d H:i:s')
            ]);

            // ✅ El modelo User maneja automáticamente UserZones en el evento restoring
            $result = $user->restore();

            if ($result) {
                Log::info('User restored successfully', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
                
                return $this->findActive($user->id);
            } else {
                Log::error('Failed to restore user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name
                ]);
                
                return null;
            }
        });
    }
}
