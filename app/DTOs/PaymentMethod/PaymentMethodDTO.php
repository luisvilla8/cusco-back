<?php

namespace App\DTOs\PaymentMethod;

class PaymentMethodDTO
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
    public static function fromModel($paymentMethod): self
    {
        return new self(
            id: $paymentMethod->id,
            code: $paymentMethod->code,
            name: $paymentMethod->name,
            description: $paymentMethod->description,
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