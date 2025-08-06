<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\ProductPriceDetailMapper.php

namespace App\Mappers;

use App\DTOs\ProductPriceDetail\ProductPriceDetailDTO;
use App\DTOs\ProductPriceDetail\ProductPriceDetailDropdownDTO;
use App\DTOs\ProductPriceDetail\ProductPricesGroupedDTO;
use App\DTOs\ProductPriceDetail\ProductZonePricesDTO;
use App\Models\ProductPriceDetail;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductPriceDetailMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(ProductPriceDetail $priceDetail): ProductPriceDetailDTO
    {
        return ProductPriceDetailDTO::fromModel($priceDetail);
    }

    /**
     * Map Collection of Models to DTOs arrays
     */
    public static function collectionToArrays($priceDetails): array
    {
        return collect($priceDetails)->map(fn($priceDetail) => self::modelToDTO($priceDetail)->toArray())->toArray();
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
     * Map paginated grouped products to DTOs
     */
    public static function paginatedGroupedToDTOs(LengthAwarePaginator $paginated): array
    {
        $groupedData = collect($paginated->items())->map(function ($productData) {
            return ProductPricesGroupedDTO::fromData($productData)->toArray();
        })->toArray();

        return [
            'data' => $groupedData,
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
    public static function collectionToDropdownDTOs($priceDetails): array
    {
        return $priceDetails->map(fn($priceDetail) => ProductPriceDetailDropdownDTO::fromModel($priceDetail)->toArray())->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(ProductPriceDetailDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * ✅ MAPPER SIMPLIFICADO
     */
    public static function massiveResultsToArrays(array $results): array
    {
        return [
            'created' => self::collectionToArrays($results['created']),
            'updated' => self::collectionToArrays($results['updated']),
            'errors' => $results['errors'],
            'summary' => [
                'total_processed' => count($results['created']) + count($results['updated']),
                'successful' => count($results['created']) + count($results['updated']),
                'created_count' => count($results['created']),
                'updated_count' => count($results['updated']),
                'errors_count' => count($results['errors']),
            ]
        ];
    }

    /**
     * Map product zone prices data to DTO
     */
    public static function productZonePricesToDTO(array $data): ProductZonePricesDTO
    {
        return ProductZonePricesDTO::fromData($data);
    }

    /**
     * ✅ MAPPER PARA CREATE ONLY
     */
    public static function massiveCreateResultsToArrays(array $results): array
    {
        return [
            'created' => self::collectionToArrays($results['created']),
            'skipped' => $results['skipped'],
            'errors' => $results['errors'],
            'summary' => [
                'total_processed' => count($results['created']) + count($results['skipped']),
                'successful' => count($results['created']),
                'created_count' => count($results['created']),
                'skipped_count' => count($results['skipped']),
                'errors_count' => count($results['errors']),
            ]
        ];
    }

    /**
     * ✅ MAPPER PARA UPDATE ONLY
     */
    public static function massiveUpdateResultsToArrays(array $results): array
    {
        return [
            'updated' => self::collectionToArrays($results['updated']),
            'not_found' => $results['not_found'],
            'errors' => $results['errors'],
            'summary' => [
                'total_processed' => count($results['updated']) + count($results['not_found']),
                'successful' => count($results['updated']),
                'updated_count' => count($results['updated']),
                'not_found_count' => count($results['not_found']),
                'errors_count' => count($results['errors']),
            ]
        ];
    }
}