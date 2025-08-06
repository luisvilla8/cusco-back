<?php

namespace App\Mappers;

use App\DTOs\User\UserDropdownDTO;
use App\DTOs\User\UserDTO;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserMapper
{
    public static function modelToDTO(User $user): UserDTO
    {
        return UserDTO::fromModel($user);
    }

    public static function collectionToArrays($users): array
    {
        return collect($users)->map(fn($user) => self::modelToDTO($user)->toArray())->toArray();
    }

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

    public static function collectionToDropdownDTOs($users): array
    {
        return $users->map(fn($user) => UserDropdownDTO::fromModel($user)->toArray())->toArray();
    }
}