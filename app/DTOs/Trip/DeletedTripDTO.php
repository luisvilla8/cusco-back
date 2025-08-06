<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Trip\DeletedTripDTO.php

namespace App\DTOs\Trip;

use App\Models\Trip;

class DeletedTripDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $deletedAt,
        public readonly bool $isForceDeleted,
    ) {}

    public static function fromModel(Trip $trip): self
    {
        return new self(
            id: $trip->id,
            name: $trip->name,
            code: $trip->code,
            deletedAt: $trip->deleted_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            isForceDeleted: !$trip->exists,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'deleted_at' => $this->deletedAt,
            'is_force_deleted' => $this->isForceDeleted,
        ];
    }
}