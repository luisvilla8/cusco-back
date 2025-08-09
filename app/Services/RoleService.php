<?php

namespace App\Services;

use App\DTOs\RoleDTO;
use App\Helpers\ResponseHelper;
use App\Mappers\RoleMapper;
use App\Repositories\RoleRepository;
use App\Traits\LoggingTrait; 

class RoleService
{
    use LoggingTrait; 

    public function __construct(
        private RoleRepository $roleRepository
    ) {}

   
    public function getAllRoles(array $filters = []): array
    {
        $this->logInfo('Fetching roles with filters', ['filters' => $filters]);

        $paginatedRoles = $this->roleRepository->getAllActiveWithPagination($filters);
        $mapped = RoleMapper::paginatedToDTOs($paginatedRoles);

        return ResponseHelper::paginated(
            $mapped['data'],
            $paginatedRoles,
            'Roles obtenidos exitosamente'
        );
    }

 
    public function getRole(int $id): array
    {
        $this->logInfo('Fetching role', ['role_id' => $id]);

        $role = $this->roleRepository->findActiveWithCount($id);

        if (!$role) {
            return ResponseHelper::notFound('Rol no encontrado o ha sido eliminado');
            
        }

        $roleDTO = RoleMapper::modelToDTO($role);
        return ResponseHelper::success($roleDTO, 'Rol obtenido exitosamente');
    }

    public function createRole(array $data): array
    {
        $this->logInfo('Creating new role', ['data' => $data]);

        $role = $this->roleRepository->create($data);
        $roleDTO = RoleMapper::modelToDTO($role);

        $this->logInfo('Role created successfully', ['role_id' => $role->id]);

        return ResponseHelper::created($roleDTO, 'Rol creado exitosamente');
    }

    public function updateRole(int $id, array $data): array
    {
        $this->logInfo('Updating role', ['role_id' => $id, 'data' => $data]);

        $role = $this->roleRepository->update($id, $data);

        if (!$role) {
            return ResponseHelper::notFound('Rol no encontrado o ha sido eliminado');
        }

        $roleDTO = RoleMapper::modelToDTO($role);

        $this->logInfo('Role updated successfully', ['role_id' => $id]);

        return ResponseHelper::success($roleDTO, 'Rol actualizado exitosamente');
    }

    /**
     *  ULTRA LIMPIO: Las validaciones están en el Model, excepciones manejadas globalmente
     */
    public function deleteRole(int $id): array
    {
        $this->logInfo('Attempting to delete role', ['role_id' => $id]);

        $role = $this->roleRepository->findActiveWithCount($id);

        if (!$role) {
            return ResponseHelper::notFound('Rol no encontrado o ha sido eliminado');
        }

        //  Si falla, Model::boot() lanza Exception → Handler global la maneja
        $deletedRole = $this->roleRepository->softDelete($id);
        $deletedRoleDTO = RoleMapper::modelToDeletedDTO($deletedRole);

        $this->logInfo('Role deleted successfully', ['role_id' => $id]);

        return ResponseHelper::success($deletedRoleDTO, 'Rol eliminado exitosamente');
    }

    /**
     *  ULTRA LIMPIO: Model valida, Handler global maneja excepciones
     */
    public function forceDeleteRole(int $id): array
    {
        $this->logInfo('Attempting to force delete role', ['role_id' => $id]);

        $role = $this->roleRepository->findWithTrashedAndCount($id);

        if (!$role) {
            return ResponseHelper::notFound('Rol no encontrado');
        }

        $roleName = $role->name;

        //  Si falla, Model::boot() lanza Exception → Handler global la maneja
        $deletedRole = $this->roleRepository->forceDelete($id);
        $deletedRoleDTO = RoleMapper::modelToDeletedDTO($deletedRole);

        $this->logWarning('Role force deleted', [
            'role_id' => $id,
            'role_name' => $roleName,
            'action' => 'PERMANENT_DELETE'
        ]);

        return ResponseHelper::success($deletedRoleDTO, "Rol '{$roleName}' eliminado permanentemente");
    }

    public function getRolesList(): array
    {
        $this->logInfo('Fetching roles list for dropdown');

        $roles = $this->roleRepository->getActiveForDropdown();
        $dropdownData = RoleMapper::collectionToDropdownDTOs($roles);

        return ResponseHelper::success($dropdownData, 'Lista de roles obtenida exitosamente');
    }
}
