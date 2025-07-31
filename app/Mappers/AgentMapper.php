<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\AgentMapper.php

namespace App\Mappers;

use App\DTOs\Agent\AgentDTO;
use App\DTOs\Agent\AgentDropdownDTO;
use App\DTOs\Agent\DeletedAgentDTO;
use App\Models\Agent;
use Illuminate\Pagination\LengthAwarePaginator;

class AgentMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(Agent $agent): AgentDTO
    {
        return AgentDTO::fromModel($agent);
    }

    /**
     * Map Model to DeletedAgentDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(Agent $agent): DeletedAgentDTO
    {
        return DeletedAgentDTO::fromModel($agent);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($agents): array
    {
        return collect($agents)->map(fn($agent) => self::modelToDTO($agent))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($agents): array
    {
        return collect($agents)->map(fn($agent) => self::modelToDTO($agent)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($agents): array
    {
        return $agents->map(fn($agent) => AgentDropdownDTO::fromModel($agent)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($agents): array
    {
        return $agents->map(fn($agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'dni' => $agent->dni,
            'ruc' => $agent->ruc,
            'agent_type_name' => $agent->agentType?->name,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(AgentDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedAgentDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedAgentDTO $dto): array
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