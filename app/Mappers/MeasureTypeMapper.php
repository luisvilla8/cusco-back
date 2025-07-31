<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\MeasureTypeMapper.php

namespace App\Mappers;

use App\DTOs\MeasureTypeDTO;
use App\DTOs\MeasureTypeDropdownDTO;
use App\DTOs\DeletedMeasureTypeDTO;
use App\Models\MeasureType;
use Illuminate\Pagination\LengthAwarePaginator;

class MeasureTypeMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(MeasureType $measureType): MeasureTypeDTO
    {
        return MeasureTypeDTO::fromModel($measureType);
    }

    /**
     * Map Model to DeletedMeasureTypeDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(MeasureType $measureType): DeletedMeasureTypeDTO
    {
        return DeletedMeasureTypeDTO::fromModel($measureType);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($measureTypes): array
    {
        return collect($measureTypes)->map(fn($measureType) => self::modelToDTO($measureType))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($measureTypes): array
    {
        return collect($measureTypes)->map(fn($measureType) => self::modelToDTO($measureType)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($measureTypes): array
    {
        return $measureTypes->map(fn($measureType) => MeasureTypeDropdownDTO::fromModel($measureType)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($measureTypes): array
    {
        return $measureTypes->map(fn($measureType) => [
            'id' => $measureType->id,
            'name' => $measureType->name,                           // ✅ CAMBIO: description → name
            'symbol' => $measureType->symbol,                       // ✅ CAMBIO: acronym → symbol
            'display_name' => "{$measureType->name} ({$measureType->symbol})", // ✅ CAMBIO
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(MeasureTypeDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedMeasureTypeDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedMeasureTypeDTO $dto): array
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