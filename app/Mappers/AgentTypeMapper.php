<?php

namespace App\Mappers;

use App\DTOs\AgentType\AgentTypeDTO;
use App\DTOs\AgentType\AgentTypeDropdownDTO;
use App\DTOs\AgentType\DeletedAgentTypeDTO;
use App\Models\AgentType;
use Illuminate\Pagination\LengthAwarePaginator;

class AgentTypeMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(AgentType $agentType): AgentTypeDTO
    {
        return AgentTypeDTO::fromModel($agentType);
    }

    /**
     * Map Model to DeletedAgentTypeDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(AgentType $agentType): DeletedAgentTypeDTO
    {
        return DeletedAgentTypeDTO::fromModel($agentType);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($agentTypes): array
    {
        return collect($agentTypes)->map(fn($agentType) => self::modelToDTO($agentType))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($agentTypes): array
    {
        return collect($agentTypes)->map(fn($agentType) => self::modelToDTO($agentType)->toArray())->toArray();
    }

    /**
     * Map paginated collection to arrays with meta (READY FOR JSON)
     */
    public static function paginatedToDTOs(LengthAwarePaginator $paginated): array
    {
        return [
            'data' => self::collectionToArrays($paginated->items()),
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
     * Map collection to dropdown arrays (READY FOR JSON)
     */
    public static function collectionToDropdownDTOs($agentTypes): array
    {
        return $agentTypes->map(fn($agentType) => AgentTypeDropdownDTO::fromModel($agentType)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($agentTypes): array
    {
        return $agentTypes->map(fn($agentType) => [
            'id' => $agentType->id,
            'name' => $agentType->name,
            'description' => $agentType->description,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(AgentTypeDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedAgentTypeDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedAgentTypeDTO $dto): array
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