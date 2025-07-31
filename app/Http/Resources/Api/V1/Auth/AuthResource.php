<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Resources\Api\V1\Auth\AuthResource.php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\User\UserResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'user' => new UserResource($this->user),
            'token' => $this->token,
            'expires_in' => $this->expires_in,
        ];
    }
}