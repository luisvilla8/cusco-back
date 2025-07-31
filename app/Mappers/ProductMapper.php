<?php

namespace App\Mappers;

use App\DTOs\Product\ProductDTO;
use App\DTOs\Product\ProductDropdownDTO;
use App\DTOs\Product\DeletedProductDTO;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(Product $product): ProductDTO
    {
        return ProductDTO::fromModel($product);
    }

    /**
     * Map Model to DeletedProductDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(Product $product): DeletedProductDTO
    {
        return DeletedProductDTO::fromModel($product);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($products): array
    {
        return collect($products)->map(fn($product) => self::modelToDTO($product))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($products): array
    {
        return collect($products)->map(fn($product) => self::modelToDTO($product)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($products): array
    {
        return $products->map(fn($product) => ProductDropdownDTO::fromModel($product)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($products): array
    {
        return $products->map(fn($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'stock' => $product->stock,
            'measure_type_symbol' => $product->measureType?->symbol,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(ProductDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedProductDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedProductDTO $dto): array
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