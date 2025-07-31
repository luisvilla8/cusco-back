<?php

namespace App\DTOs\AgentType;

class AgentTypeDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($agentType): self
    {
        return new self(
            id: $agentType->id,
            name: $agentType->name,
            description: $agentType->description,
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
        ];
    }
}