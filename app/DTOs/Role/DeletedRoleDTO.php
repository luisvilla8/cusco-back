<?php

namespace App\DTOs\Role;

class DeletedRoleDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt, 
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
            createdAt: $role->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $role->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $role->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}