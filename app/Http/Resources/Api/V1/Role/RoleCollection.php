<?php

namespace App\Http\Resources\Api\V1\Role;

use Illuminate\Http\Resources\Json\ResourceCollection;

class RoleCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return [
            'data' => RoleResource::collection($this->collection),
            'meta' => $this->when($this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator, [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ]),
        ];
    }
}