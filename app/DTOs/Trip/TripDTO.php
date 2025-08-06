<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Trip\TripDTO.php

namespace App\DTOs\Trip;

use App\Models\Trip;

class TripDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $description,
        public readonly float $travelExpenses,
        public readonly float $total,
        public readonly string $dateStart,
        public readonly string $dateEnd,
        public readonly int $durationDays,
        public readonly string $status,
        public readonly string $statusText,
        public readonly array $agent,
        public readonly array $zone,
        public readonly array $user,
        public readonly array $summary,
        public readonly array $travelExpenseEgress, // ✅ NUEVO
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromModel(Trip $trip): self
    {
        // ✅ OBTENER EGRESO DE GASTOS DE VIAJE
        $travelExpenseEgress = $trip->getTravelExpenseEgress();

        return new self(
            id: $trip->id,
            name: $trip->name,
            code: $trip->code,
            description: $trip->description,
            travelExpenses: (float) $trip->travel_expenses,
            total: (float) $trip->total,
            dateStart: $trip->date_start->format('Y-m-d'),
            dateEnd: $trip->date_end->format('Y-m-d'),
            durationDays: $trip->duration_days,
            status: $trip->trip_status,
            statusText: $trip->trip_status_text,
            agent: [
                'id' => $trip->agent?->id,
                'name' => $trip->agent?->name,
                'code' => $trip->agent?->code,
            ],
            zone: [
                'id' => $trip->zone?->id,
                'name' => $trip->zone?->name,
                'code' => $trip->zone?->code,
            ],
            user: [
                'id' => $trip->user?->id,
                'name' => $trip->user?->name,
                'code' => $trip->user?->code ?? null,
                'email' => $trip->user?->email,
            ],
            summary: $trip->trip_summary ?? [],
            // ✅ INFORMACIÓN DEL EGRESO ASOCIADO
            travelExpenseEgress: $travelExpenseEgress ? [
                'id' => $travelExpenseEgress->id,
                'code' => $travelExpenseEgress->code,
                'name' => $travelExpenseEgress->name,
                'amount' => (float) $travelExpenseEgress->amount,
                'formatted_amount' => $travelExpenseEgress->formatted_amount,
                'date' => $travelExpenseEgress->date->format('Y-m-d'),
                'created_at' => $travelExpenseEgress->created_at->format('Y-m-d H:i:s'),
            ] : [],
            createdAt: $trip->created_at->format('Y-m-d H:i:s'),
            updatedAt: $trip->updated_at->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'travel_expenses' => $this->travelExpenses,
            'total' => $this->total,
            'date_start' => $this->dateStart,
            'date_end' => $this->dateEnd,
            'duration_days' => $this->durationDays,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'agent' => $this->agent,
            'zone' => $this->zone,
            'user' => $this->user,
            'summary' => $this->summary,
            'travel_expense_egress' => $this->travelExpenseEgress, // ✅ INCLUIR EN RESPUESTA
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}