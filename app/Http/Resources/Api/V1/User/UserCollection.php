<?php

namespace App\Http\Resources\Api\V1\User;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return [
            'data' => UserResource::collection($this->collection),
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