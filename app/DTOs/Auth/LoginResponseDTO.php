<?php

namespace App\DTOs\Auth;

class LoginResponseDTO
{
    public function __construct(
        public readonly AuthUserDTO $user,
        public readonly string $token,
        public readonly int $expiresIn,
        public readonly string $tokenType = 'Bearer',
    ) {}

    public static function fromData($user, string $token, int $expiresIn): self
    {
        return new self(
            user: AuthUserDTO::fromModel($user),
            token: $token,
            expiresIn: $expiresIn,
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'token' => $this->token,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'expires_at' => now()->addSeconds($this->expiresIn)->format('Y-m-d H:i:s'),
        ];
    }
}