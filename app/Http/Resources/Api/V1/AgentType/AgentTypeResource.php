<?php

namespace App\Http\Resources\Api\V1\AgentType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive(),
            'agents_count' => $this->agents_count ?? 0,
            'active_agents_count' => $this->active_agents_count ?? 0,
            'can_be_deleted' => $this->canBeDeleted(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Incluir agentes si están cargados
            'agents' => $this->whenLoaded('agents'),
            'active_agents' => $this->whenLoaded('activeAgents'),
        ];
    }
}