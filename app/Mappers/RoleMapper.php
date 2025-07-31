<?php

namespace App\Mappers;

use App\DTOs\Role\DeletedRoleDTO;
use App\DTOs\Role\RoleDropdownDTO;
use App\DTOs\Role\RoleDTO;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(Role $role): RoleDTO
    {
        return RoleDTO::fromModel($role);
    }

    /**
     * Map Model to DeletedRoleDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(Role $role): DeletedRoleDTO
    {
        return DeletedRoleDTO::fromModel($role);
    }

    /**
     * ✅ CORREGIDO: Map Collection of Models to DTOs (sin convertir a array aún)
     */
    public static function collectionToDTOs($roles): array
    {
        return collect($roles)->map(fn($role) => self::modelToDTO($role))->toArray();
    }

    /**
     * ✅ NUEVO: Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($roles): array
    {
        return collect($roles)->map(fn($role) => self::modelToDTO($role)->toArray())->toArray();
    }

    /**
     * ✅ CORREGIDO: Map paginated collection to arrays with meta (READY FOR JSON)
     */
    public static function paginatedToDTOs(LengthAwarePaginator $paginated): array
    {
        return [
            'data' => self::collectionToArrays($paginated->items()), // ✅ ARRAYS en lugar de DTOs
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'has_more_pages' => $paginated->hasMorePages(),
            ]
        ];
    }

    /**
     * ✅ ACTUALIZADO: Map collection to dropdown arrays (READY FOR JSON)
     */
    public static function collectionToDropdownDTOs($roles): array
    {
        return $roles->map(fn($role) => RoleDropdownDTO::fromModel($role)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($roles): array
    {
        return $roles->map(fn($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(RoleDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedRoleDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedRoleDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map multiple DTOs to arrays
     */
    public static function dtosToArrays(array $dtos): array
    {
        return array_map(fn($dto) => $dto->toArray(), $dtos);
    }

    /**
     * Map dropdown DTOs to arrays
     */
    public static function dropdownDTOsToArrays(array $dtos): array
    {
        return array_map(fn($dto) => $dto->toArray(), $dtos);
    }
}