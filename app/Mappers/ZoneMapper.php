<?php

namespace App\Mappers;

use App\DTOs\Zone\ZoneDTO;
use App\DTOs\Zone\ZoneDropdownDTO;
use App\DTOs\Zone\DeletedZoneDTO;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;

class ZoneMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(Zone $zone): ZoneDTO
    {
        return ZoneDTO::fromModel($zone);
    }

    /**
     * Map Model to DeletedZoneDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(Zone $zone): DeletedZoneDTO
    {
        return DeletedZoneDTO::fromModel($zone);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($zones): array
    {
        return collect($zones)->map(fn($zone) => self::modelToDTO($zone))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($zones): array
    {
        return collect($zones)->map(fn($zone) => self::modelToDTO($zone)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($zones): array
    {
        return $zones->map(fn($zone) => ZoneDropdownDTO::fromModel($zone)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($zones): array
    {
        return $zones->map(fn($zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'description' => $zone->description,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(ZoneDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedZoneDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedZoneDTO $dto): array
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