<?php

namespace App\DTOs\AgentType;

class AgentTypeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $agentsCount,
        public readonly string $createdAt,
        public readonly string $updatedAt,
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
            agentsCount: $agentType->agents_count ?? 0,
            createdAt: $agentType->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $agentType->updated_at?->format('Y-m-d H:i:s') ?? '',
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
            'agents_count' => $this->agentsCount,
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * ✅ SIMPLIFICADO: Solo verificar si tiene agentes
     */
    public function canBeDeleted(): bool
    {
        return $this->agentsCount === 0;
    }
}