<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\UserService.php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(
        private User $user
    ) {}

    /**
     * Create a new user
     */
    public function createUser(array $data): array
    {
        try {
            DB::beginTransaction();

            // Si no se especifica role_id, asignar rol por defecto
            if (!isset($data['role_id']) || empty($data['role_id'])) {
                $defaultRole = Role::where('name', 'User')->first();
                if (!$defaultRole) {
                    // Crear rol por defecto si no existe
                    $defaultRole = Role::create([
                        'name' => 'User',
                        'description' => 'Usuario regular'
                    ]);
                }
                $data['role_id'] = $defaultRole->id;
            } else {
                // Verificar que el rol existe
                $role = Role::find($data['role_id']);
                if (!$role) {
                    return $this->errorResponse('Role not found', 404);
                }
            }

            $user = $this->user->create($data);
            $user->load('role');

            DB::commit();

            return $this->createdResponse($user, 'User created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Get all users with pagination and filters
     */
    public function getAllUsers(array $filters = []): array
    {
        try {
            // Solo usuarios activos por defecto
            $query = $this->user->active()->with('role');

            // Incluir eliminados si se especifica
            if (!empty($filters['include_deleted'])) {
                $query = $this->user->withTrashed()->with('role');
            }

            // Apply search filter
            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                });
            }

            // Filter by role
            if (!empty($filters['role_id'])) {
                $query->where('role_id', $filters['role_id']);
            }

            // Apply ordering
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $users = $query->paginate($filters['per_page'] ?? 15);

            return [
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                    'has_more_pages' => $users->hasMorePages(),
                ],
                'message' => 'Users retrieved successfully',
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Get trashed users
     */
    public function getTrashedUsers(array $filters = []): array
    {
        try {
            $query = $this->user->onlyTrashed()->with('role');

            // Apply search filter
            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                });
            }

            $users = $query->paginate($filters['per_page'] ?? 15);

            return [
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                    'has_more_pages' => $users->hasMorePages(),
                ],
                'message' => 'Trashed users retrieved successfully',
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching trashed users: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Get a specific user by ID
     */
    public function getUser(int $id): array
    {
        try {
            $user = $this->user->active()->with('role')->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            return $this->successResponse($user, 'User retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser(int $id, array $data): array
    {
        try {
            $user = $this->user->active()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            // Verificar que el rol existe si se está cambiando
            if (isset($data['role_id'])) {
                $role = Role::find($data['role_id']);
                if (!$role) {
                    return $this->errorResponse('Role not found', 404);
                }
            }

            DB::beginTransaction();

            $user->update($data);
            $user->load('role');

            DB::commit();

            return $this->successResponse($user, 'User updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Soft delete a user
     */
    public function deleteUser(int $id): array
    {
        try {
            $user = $this->user->active()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            DB::beginTransaction();

            $user->delete(); // Soft delete

            DB::commit();

            return $this->successResponse([], 'User deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Restore a soft deleted user
     */
    public function restoreUser(int $id): array
    {
        try {
            $user = $this->user->withTrashed()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            if (!$user->trashed()) {
                return $this->errorResponse('User is not deleted', 400);
            }

            DB::beginTransaction();

            $user->restore();
            $user->load('role');

            DB::commit();

            return $this->successResponse($user, 'User restored successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error restoring user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Permanently delete a user
     */
    public function forceDeleteUser(int $id): array
    {
        try {
            $user = $this->user->withTrashed()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            DB::beginTransaction();

            $user->forceDelete(); // Permanent delete

            DB::commit();

            return $this->successResponse([], 'User permanently deleted');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error force deleting user: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(int $id, array $data): array
    {
        try {
            $user = $this->user->active()->find($id);

            if (!$user) {
                return $this->notFoundResponse('User not found');
            }

            // Verificar contraseña actual
            if (!Hash::check($data['current_password'], $user->password)) {
                return $this->errorResponse('Current password is incorrect', 400);
            }

            DB::beginTransaction();

            $user->update(['password' => $data['new_password']]);

            DB::commit();

            return $this->successResponse([], 'Password changed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error changing password: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }
}