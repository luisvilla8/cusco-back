<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Services\UserService.php

namespace App\Services;

use App\Helpers\ResponseHelper;
use App\Mappers\UserMapper;
use App\Repositories\UserRepository;
use App\Traits\LoggingTrait;

class UserService
{
    use LoggingTrait;

    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function getAllUsers(array $filters = []): array
    {
        $this->logInfo('Fetching users with filters', ['filters' => $filters]);

        $paginatedUsers = $this->userRepository->getAllActiveWithPagination($filters);
        $mapped = UserMapper::paginatedToDTOs($paginatedUsers);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedUsers,
            'Usuarios obtenidos exitosamente'
        );
    }

    public function getUser(int $id): array
    {
        $this->logInfo('Fetching user', ['user_id' => $id]);

        $user = $this->userRepository->findActive($id);

        if (!$user) {
            return ResponseHelper::notFound('Usuario no encontrado');
        }

        $userDTO = UserMapper::modelToDTO($user);
        return ResponseHelper::success($userDTO->toArray(), 'Usuario obtenido exitosamente');
    }

    public function getUsersList(): array
    {
        $this->logInfo('Fetching users list for dropdown');

        $users = $this->userRepository->getActiveForDropdown();
        $dropdownData = UserMapper::collectionToDropdownDTOs($users);

        return ResponseHelper::success($dropdownData, 'Lista de usuarios obtenida exitosamente');
    }

    public function createUser(array $data): array
    {
        $this->logInfo('Creating user', ['email' => $data['email']]);

        try {
            $user = $this->userRepository->create($data);
            $userDTO = UserMapper::modelToDTO($user);

            $this->logInfo('User created successfully', ['user_id' => $user->id, 'user_code' => $user->code]);

            return ResponseHelper::created($userDTO->toArray(), 'Usuario creado exitosamente');
        } catch (\Exception $e) {
            $this->logError('Error creating user', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Error al crear el usuario: ' . $e->getMessage());
        }
    }

    public function updateUser(int $id, array $data): array
    {
        $this->logInfo('Updating user', ['user_id' => $id]);

        try {
            $user = $this->userRepository->update($id, $data);

            if (!$user) {
                return ResponseHelper::notFound('Usuario no encontrado');
            }

            $userDTO = UserMapper::modelToDTO($user);

            $this->logInfo('User updated successfully', ['user_id' => $user->id]);

            return ResponseHelper::success($userDTO->toArray(), 'Usuario actualizado exitosamente');
        } catch (\Exception $e) {
            $this->logError('Error updating user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al actualizar el usuario: ' . $e->getMessage());
        }
    }

    public function deleteUser(int $id): array
    {
        $this->logInfo('Soft deleting user', ['user_id' => $id]);

        try {
            $deleted = $this->userRepository->delete($id);

            if (!$deleted) {
                return ResponseHelper::notFound('Usuario no encontrado');
            }

            $this->logInfo('User soft deleted successfully', ['user_id' => $id]);

            return ResponseHelper::success([], 'Usuario eliminado exitosamente');
        } catch (\Exception $e) {
            $this->logError('Error soft deleting user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al eliminar el usuario: ' . $e->getMessage());
        }
    }

    public function forceDeleteUser(int $id): array
    {
        $this->logInfo('Force deleting user', ['user_id' => $id]);

        try {
            $deleted = $this->userRepository->forceDelete($id);

            if (!$deleted) {
                return ResponseHelper::notFound('Usuario no encontrado');
            }

            $this->logInfo('User force deleted successfully', ['user_id' => $id]);

            return ResponseHelper::success([], 'Usuario eliminado permanentemente');
        } catch (\Exception $e) {
            $this->logError('Error force deleting user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al eliminar permanentemente el usuario: ' . $e->getMessage());
        }
    }

    /**
     *  NUEVO: RESTAURAR USUARIO
     */
    public function restoreUser(int $id): array
    {
        $this->logInfo('Restoring user', ['user_id' => $id]);

        try {
            $user = $this->userRepository->restore($id);

            if (!$user) {
                return ResponseHelper::notFound('Usuario eliminado no encontrado');
            }

            $userDTO = UserMapper::modelToDTO($user);

            $this->logInfo('User restored successfully', ['user_id' => $user->id]);

            return ResponseHelper::success($userDTO->toArray(), 'Usuario restaurado exitosamente');
        } catch (\Exception $e) {
            $this->logError('Error restoring user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error('Error al restaurar el usuario: ' . $e->getMessage());
        }
    }
}