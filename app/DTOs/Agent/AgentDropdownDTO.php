<?php

namespace App\DTOs\Agent;

class AgentDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $dni,
        public readonly ?string $ruc,
        public readonly ?string $agentTypeName,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($agent): self
    {
        return new self(
            id: $agent->id,
            name: $agent->name,
            dni: $agent->dni,
            ruc: $agent->ruc,
            agentTypeName: $agent->agentType?->name ?? $agent->agent_type_name ?? null,
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
            'dni' => $this->dni,
            'ruc' => $this->ruc,
            'agent_type_name' => $this->agentTypeName,
            'display_name' => $this->getDisplayName(),
        ];
    }

    /**
     * Get formatted display name
     */
    private function getDisplayName(): string
    {
        $parts = [$this->name];
        
        if ($this->dni) {
            $parts[] = "DNI: {$this->dni}";
        }
        
        if ($this->ruc) {
            $parts[] = "RUC: {$this->ruc}";
        }
        
        return implode(' - ', $parts);
    }
}