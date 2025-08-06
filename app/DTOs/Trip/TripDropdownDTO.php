<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Trip\TripDropdownDTO.php

namespace App\DTOs\Trip;

use App\Models\Trip;

class TripDropdownDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $displayName,
        public readonly string $status,
        public readonly string $dateRange,
    ) {}

    public static function fromModel(Trip $trip): self
    {
        return new self(
            id: $trip->id,
            name: $trip->name,
            code: $trip->code,
            displayName: $trip->display_name,
            status: $trip->trip_status,
            dateRange: $trip->date_range_text,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'display_name' => $this->displayName,
            'status' => $this->status,
            'date_range' => $this->dateRange,
        ];
    }
}