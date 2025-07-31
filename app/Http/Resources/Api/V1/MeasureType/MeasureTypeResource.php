<?php

namespace App\Http\Resources\Api\V1\MeasureType;

use Illuminate\Http\Resources\Json\JsonResource;

class MeasureTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'acronym' => $this->acronym,
            'display_name' => $this->display_name,
            'products_count' => $this->whenCounted('products'),
            'can_be_deleted' => !$this->hasProducts(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
