<?php

namespace App\DTOs\PaymentMethod;

class PaymentMethodDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
    ) {}

    public static function fromModel($paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            code: $paymentMethod->code,
            name: $paymentMethod->name,
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