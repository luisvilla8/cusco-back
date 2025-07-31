<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Resources\Api\V1\Role\DeletedRoleResource.php

namespace App\Http\Resources\Api\V1\Role;

use Illuminate\Http\Resources\Json\JsonResource;

class DeletedRoleResource extends JsonResource
{
    /**
     * Transform the deleted role resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'users_count' => $this->users_count ?? 0,
            'is_deleted' => $this->is_deleted ?? true,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}