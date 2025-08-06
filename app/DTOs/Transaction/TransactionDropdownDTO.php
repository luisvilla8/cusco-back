<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Transaction\TransactionDropdownDTO.php

namespace App\DTOs\Transaction;

class TransactionDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $displayName,
        public readonly string $agentName,
        public readonly float $total,
        public readonly string $date,
        public readonly string $status // ✅ REMOVER ? PARA QUE SEA OBLIGATORIO
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'display_name' => $this->displayName,
            'agent_name' => $this->agentName,
            'total' => $this->total,
            'date' => $this->date,
            'status' => $this->status
        ];
    }
}