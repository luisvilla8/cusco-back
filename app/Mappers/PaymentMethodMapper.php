<?php

namespace App\Mappers;

use App\DTOs\PaymentMethod\PaymentMethodDropdownDTO;
use App\DTOs\PaymentMethod\PaymentMethodDTO;
use App\Models\PaymentMethod;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(PaymentMethod $paymentMethod): PaymentMethodDTO
    {
        return PaymentMethodDTO::fromModel($paymentMethod);
    }

    /**
     * Map Collection of Models to DTOs arrays
     */
    public static function collectionToArrays($paymentMethods): array
    {
        return collect($paymentMethods)->map(fn($paymentMethod) => self::modelToDTO($paymentMethod)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($paymentMethods): array
    {
        return $paymentMethods->map(fn($paymentMethod) => PaymentMethodDropdownDTO::fromModel($paymentMethod)->toArray())->toArray();
    }
}