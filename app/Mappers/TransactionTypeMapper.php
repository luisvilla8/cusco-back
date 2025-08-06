<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\TransactionTypeMapper.php

namespace App\Mappers;

use App\DTOs\TransactionType\TransactionTypeDropdownDTO;
use App\DTOs\TransactionType\TransactionTypeDTO;
use App\Models\TransactionType;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionTypeMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(TransactionType $transactionType): TransactionTypeDTO
    {
        return TransactionTypeDTO::fromModel($transactionType);
    }

    /**
     * Map Collection of Models to DTOs arrays
     */
    public static function collectionToArrays($transactionTypes): array
    {
        return collect($transactionTypes)->map(fn($transactionType) => self::modelToDTO($transactionType)->toArray())->toArray();
    }

    /**
     * Map paginated collection to arrays with meta
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
     * Map collection to dropdown arrays
     */
    public static function collectionToDropdownDTOs($transactionTypes): array
    {
        return $transactionTypes->map(fn($transactionType) => TransactionTypeDropdownDTO::fromModel($transactionType)->toArray())->toArray();
    }
}