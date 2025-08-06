<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\TransactionType\TransactionTypeDropdownDTO.php

namespace App\DTOs\TransactionType;

class TransactionTypeDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
    ) {}

    public static function fromModel($transactionType): self
    {
        return new self(
            id: $transactionType->id,
            code: $transactionType->code,
            name: $transactionType->name,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}