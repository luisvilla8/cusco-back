<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\User\UserDTO.php

namespace App\DTOs\User;

class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly int $roleId,
        public readonly string $roleName,
        public readonly string $roleCode,
        public readonly ?string $primaryZoneName, // ✅ CAMBIAR
        public readonly array $zones, // ✅ AGREGAR array de zonas
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromModel($user): self
    {
        return new self(
            id: $user->id,
            code: $user->code,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            roleId: $user->role_id,
            roleName: $user->role?->name ?? 'Sin rol',
            roleCode: $user->role?->code ?? 'NO_ROLE',
            primaryZoneName: $user->primary_zone_name, // ✅ USAR helper del modelo
            zones: $user->zones->map(fn($zone) => [ // ✅ MAPEAR todas las zonas
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code
            ])->toArray(),
            createdAt: $user->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $user->updated_at?->format('Y-m-d H:i:s') ?? '',
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
                'id' => $this->roleId,
                'name' => $this->roleName,
                'code' => $this->roleCode,
            ],
            'primary_zone' => $this->primaryZoneName, // ✅ NOMBRE de zona principal
            'zones' => $this->zones, // ✅ ARRAY completo de zonas
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}