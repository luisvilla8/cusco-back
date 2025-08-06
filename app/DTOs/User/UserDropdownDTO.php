<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\User\UserDropdownDTO.php

namespace App\DTOs\User;

class UserDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $roleName,
    ) {}

    public static function fromModel($user): self
    {
        return new self(
            id: $user->id,
            code: $user->code,
            name: $user->name,
            roleName: $user->role?->name ?? 'Sin rol',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'role' => $this->roleName,
        ];
    }
}