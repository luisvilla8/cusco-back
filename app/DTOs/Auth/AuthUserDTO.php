<?php

namespace App\DTOs\Auth;

class AuthUserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $roleName,
        public readonly string $roleCode,
        public readonly ?string $primaryZoneName, // ✅ CAMBIAR nombre
        public readonly array $zones, // ✅ AGREGAR todas las zonas
        public readonly string $createdAt,
    ) {}

    public static function fromModel($user): self
    {
        return new self(
            id: $user->id,
            code: $user->code,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            roleName: $user->role?->name ?? 'Sin rol',
            roleCode: $user->role?->code ?? 'NO_ROLE',
            primaryZoneName: $user->primary_zone_name, // ✅ USAR helper
            zones: $user->zones->map(fn($zone) => [ // ✅ TODAS las zonas
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code
            ])->toArray(),
            createdAt: $user->created_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => [
                'name' => $this->roleName,
                'code' => $this->roleCode,
            ],
            'primary_zone' => $this->primaryZoneName,
            'zones' => $this->zones, // ✅ ARRAY de zonas
            'created_at' => $this->createdAt,
        ];
    }
}