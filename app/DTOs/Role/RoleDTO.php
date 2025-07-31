<?php

namespace App\DTOs\Role;

class RoleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $usersCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            description: $role->description,
            usersCount: $role->users_count ?? 0, 
            createdAt: $role->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $role->updated_at?->format('Y-m-d H:i:s') ?? '',
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'users_count' => $this->usersCount,
            'can_be_deleted' => $this->canBeDeleted(), 
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * LÓGICA: Calculada dinámicamente
     */
    public function canBeDeleted(): bool
    {
        return $this->usersCount === 0 
            && !in_array($this->name, ['Administrador', 'Super Admin'])  //  Proteger roles críticos
            && $this->id !== 1;                                          //  Proteger rol principal
    }
}