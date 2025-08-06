<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Repositories\AuthRepository.php

namespace App\Repositories;

use App\Models\User;
use App\Models\Role;
use App\Models\Zone;
use App\Models\UserZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthRepository
{
    public function __construct(
        private User $userModel,
        private Role $roleModel,
        private Zone $zoneModel
    ) {}

    public function findUserByEmail(string $email): ?User
    {
        return $this->userModel->active()
            ->where('email', $email)
            ->with(['role', 'zones'])
            ->first();
    }

    public function findUserWithTokens(int $id): ?User
    {
        return $this->userModel->active()
            ->with(['role', 'zones'])
            ->find($id);
    }

    /**
     * ✅ AUTO-REGISTRO: Solo rol por defecto, zona opcional
     */
    public function createUserFromRegistration(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $defaultRole = $this->getDefaultUserRole();
            
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'role_id' => $defaultRole->id,
            ];

            $user = $this->userModel->create($userData);
            
            // ✅ ASIGNAR zona por defecto si no hay zonas específicas
            $defaultZone = $this->getDefaultZone();
            if ($defaultZone) {
                UserZone::create([
                    'user_id' => $user->id,
                    'zone_id' => $defaultZone->id
                ]);
            }
            
            return $this->findUserWithTokens($user->id);
        });
    }

    /**
     * ✅ GESTIÓN ADMIN: Permite múltiples zonas
     */
    public function createUserFromAdmin(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'role_id' => $data['role_id'],
            ];

            $user = $this->userModel->create($userData);
            
            // ✅ ASIGNAR múltiples zonas si se proporcionan
            if (!empty($data['zone_ids']) && is_array($data['zone_ids'])) {
                foreach ($data['zone_ids'] as $zoneId) {
                    UserZone::create([
                        'user_id' => $user->id,
                        'zone_id' => $zoneId
                    ]);
                }
            }
            
            return $this->findUserWithTokens($user->id);
        });
    }

    /**
     * ✅ ACTUALIZAR usuario con múltiples zonas (INTELIGENTE)
     */
    public function updateUserZones(int $userId, ?array $zoneIds): bool
    {
        return DB::transaction(function () use ($userId, $zoneIds) {
            Log::info('Updating user zones with intelligent management', [
                'user_id' => $userId,
                'new_zone_ids' => $zoneIds
            ]);

            if (empty($zoneIds)) {
                // ✅ Si no hay zonas, marcar todas como eliminadas
                UserZone::where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->delete(); // Soft delete
                
                Log::info('All user zones soft deleted', ['user_id' => $userId]);
                return true;
            }

            // ✅ OBTENER todas las asignaciones (incluidas eliminadas)
            $allUserZones = UserZone::withTrashed()
                ->where('user_id', $userId)
                ->get()
                ->keyBy('zone_id');

            // ✅ PROCESAR cada zona nueva
            $uniqueZoneIds = array_unique($zoneIds);
            foreach ($uniqueZoneIds as $zoneId) {
                if (!is_numeric($zoneId) || $zoneId <= 0) continue;
                
                $existingAssignment = $allUserZones->get($zoneId);
                
                if ($existingAssignment) {
                    // ✅ RESTAURAR si está eliminada
                    if ($existingAssignment->deleted_at) {
                        $existingAssignment->restore();
                        Log::info('Restored zone assignment', [
                            'user_id' => $userId,
                            'zone_id' => $zoneId
                        ]);
                    }
                } else {
                    // ✅ CREAR nueva asignación
                    UserZone::create([
                        'user_id' => $userId,
                        'zone_id' => (int) $zoneId
                    ]);
                    Log::info('Created new zone assignment', [
                        'user_id' => $userId,
                        'zone_id' => $zoneId
                    ]);
                }
            }

            // ✅ SOFT DELETE zonas que ya no están en la lista
            UserZone::where('user_id', $userId)
                ->whereNull('deleted_at')
                ->whereNotIn('zone_id', $uniqueZoneIds)
                ->delete(); // Soft delete

            return true;
        });
    }

    public function updateUserPassword(int $userId, string $hashedPassword): bool
    {
        return $this->userModel->where('id', $userId)->update([
            'password' => $hashedPassword
        ]);
    }

    public function revokeUserToken(User $user, ?string $tokenId = null): bool
    {
        if ($tokenId) {
            return $user->tokens()->where('id', $tokenId)->delete();
        }
        
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            return $user->tokens()->where('id', $currentToken->id)->delete();
        }
        
        return true;
    }

    public function revokeAllUserTokens(User $user): bool
    {
        return $user->tokens()->delete();
    }

    private function getDefaultUserRole(): Role
    {
        $defaultRole = $this->roleModel->where('name', 'Usuario')->first();
        
        if (!$defaultRole) {
            $defaultRole = $this->roleModel->create([
                'name' => 'Usuario',
                'description' => 'Usuario regular del sistema'
            ]);
        }
        
        return $defaultRole;
    }

    private function getDefaultZone(): ?Zone
    {
        return $this->zoneModel->active()->first();
    }
}