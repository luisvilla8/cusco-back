<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\TransactionType\TransactionTypeDTO.php

namespace App\DTOs\TransactionType;

class TransactionTypeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
    ) {}

    /**
     * Create from Eloquent Model
     */
    public static function fromModel($transactionType): self
    {
        return new self(
            id: $transactionType->id,
            code: $transactionType->code,
            name: $transactionType->name,
            description: $transactionType->description,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}