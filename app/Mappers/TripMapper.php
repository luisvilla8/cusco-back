<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\TripMapper.php

namespace App\Mappers;

use App\DTOs\Trip\{DeletedTripDTO, TripDropdownDTO, TripDTO};
use App\Models\Trip;
use Illuminate\Pagination\LengthAwarePaginator;

class TripMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(Trip $trip): TripDTO
    {
        return TripDTO::fromModel($trip);
    }

    /**
     * Map Model to DeletedTripDTO
     */
    public static function modelToDeletedDTO(Trip $trip): DeletedTripDTO
    {
        return DeletedTripDTO::fromModel($trip);
    }

    /**
     * Map Collection to arrays (ready for JSON)
     */
    public static function collectionToArrays($trips): array
    {
        return collect($trips)->map(fn($trip) => self::modelToDTO($trip)->toArray())->toArray();
    }

    /**
     * Map paginated collection to arrays with meta
     */
    public static function paginatedToDTOs(LengthAwarePaginator $paginated): array
    {
        return [
            'data' => self::collectionToArrays($paginated->items()),
            'meta' => [
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                    'has_more_pages' => $paginated->hasMorePages(),
                ]
            ]
        ];
    }

    /**
     * Map collection to dropdown arrays
     */
    public static function collectionToDropdownDTOs($trips): array
    {
        return $trips->map(fn($trip) => TripDropdownDTO::fromModel($trip)->toArray())->toArray();
    }
}