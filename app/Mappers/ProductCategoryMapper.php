<?php

namespace App\Mappers;

use App\DTOs\ProductCategory\DeletedProductCategoryDTO;
use App\DTOs\ProductCategory\ProductCategoryDropdownDTO;
use App\DTOs\ProductCategory\ProductCategoryDTO;
use App\Models\ProductCategory;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductCategoryMapper
{
    /**
     * Map Model to DTO
     */
    public static function modelToDTO(ProductCategory $productCategory): ProductCategoryDTO
    {
        return ProductCategoryDTO::fromModel($productCategory);
    }

    /**
     * Map Model to DeletedProductCategoryDTO (para eliminaciones)
     */
    public static function modelToDeletedDTO(ProductCategory $productCategory): DeletedProductCategoryDTO
    {
        return DeletedProductCategoryDTO::fromModel($productCategory);
    }

    /**
     * Map Collection of Models to DTOs
     */
    public static function collectionToDTOs($productCategories): array
    {
        return collect($productCategories)->map(fn($productCategory) => self::modelToDTO($productCategory))->toArray();
    }

    /**
     * Map Collection of Models to arrays (ready for JSON)
     */
    public static function collectionToArrays($productCategories): array
    {
        return collect($productCategories)->map(fn($productCategory) => self::modelToDTO($productCategory)->toArray())->toArray();
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
    public static function collectionToDropdownDTOs($productCategories): array
    {
        return $productCategories->map(fn($productCategory) => ProductCategoryDropdownDTO::fromModel($productCategory)->toArray())->toArray();
    }

    /**
     * Map collection to simple dropdown arrays
     */
    public static function collectionToDropdownArrays($productCategories): array
    {
        return $productCategories->map(fn($productCategory) => [
            'id' => $productCategory->id,
            'name' => $productCategory->name,
            'description' => $productCategory->description,
        ])->toArray();
    }

    /**
     * Map DTO to array for API response
     */
    public static function dtoToArray(ProductCategoryDTO $dto): array
    {
        return $dto->toArray();
    }

    /**
     * Map DeletedProductCategoryDTO to array for API response
     */
    public static function deletedDtoToArray(DeletedProductCategoryDTO $dto): array
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