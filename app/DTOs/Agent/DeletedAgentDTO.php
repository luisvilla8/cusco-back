<?php

namespace App\DTOs\Agent;

class DeletedAgentDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $dni,
        public readonly ?string $ruc,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $deletedAt,
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
            email: $agent->email,
            dni: $agent->dni,
            ruc: $agent->ruc,
            createdAt: $agent->created_at?->format('Y-m-d H:i:s') ?? '',
            updatedAt: $agent->updated_at?->format('Y-m-d H:i:s') ?? '',
            deletedAt: $agent->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
        );
    }

   
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'dni' => $this->dni,
            'ruc' => $this->ruc,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }
}