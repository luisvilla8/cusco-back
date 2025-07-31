<?php

namespace App\DTOs\Role;

class RoleDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
    ) {}


    public static function fromModel($role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            description: $role->description,
        );
    }

  
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}