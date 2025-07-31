<?php

namespace App\DTOs\Agent;

class AgentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly ?string $email,
        public readonly ?string $dni,
        public readonly ?string $ruc,
        public readonly int $agentTypeId,
        public readonly ?string $agentTypeName,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($agent): self
    {
        return new self(
            id: $agent->id,
            name: $agent->name,
            phone: $agent->phone,
            address: $agent->address,
            email: $agent->email,
            dni: $agent->dni,
            ruc: $agent->ruc,
            agentTypeId: $agent->agent_type_id,
            agentTypeName: $agent->agentType?->name ?? $agent->agent_type_name ?? null,
            createdAt: $agent->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $agent->updated_at?->format('Y-m-d H:i:s') ?? '',
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
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'dni' => $this->dni,
            'ruc' => $this->ruc,
            'agent_type_id' => $this->agentTypeId,
            'agent_type_name' => $this->agentTypeName,
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * ✅ SIMPLIFICADO: Siempre puede eliminarse (no tiene dependencias críticas)
     */
    public function canBeDeleted(): bool
    {
        return true; // Los agentes normalmente pueden eliminarse siempre
    }
}