<?php

namespace App\Mappers;

use App\DTOs\Auth\AuthUserDTO;
use App\DTOs\Auth\LoginResponseDTO;
use App\DTOs\Auth\RegisterResponseDTO;

class AuthMapper
{
    public static function userToAuthDTO($user): AuthUserDTO
    {
        return AuthUserDTO::fromModel($user);
    }

    public static function toLoginResponse($user, string $token, int $expiresIn): LoginResponseDTO
    {
        return LoginResponseDTO::fromData($user, $token, $expiresIn);
    }

    public static function toRegisterResponse($user, string $token, int $expiresIn): RegisterResponseDTO
    {
        return RegisterResponseDTO::fromData($user, $token, $expiresIn);
    }

    public static function loginResponseToArray(LoginResponseDTO $dto): array
    {
        return $dto->toArray();
    }

    public static function registerResponseToArray(RegisterResponseDTO $dto): array
    {
        return $dto->toArray();
    }

    public static function authUserToArray(AuthUserDTO $dto): array
    {
        return $dto->toArray();
    }
}