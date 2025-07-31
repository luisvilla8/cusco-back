<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Resources\Api\V1\Agent\AgentResource.php

namespace App\Http\Resources\Api\V1\Agent;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\AgentType\AgentTypeResource;

class AgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'formatted_phone' => $this->formatted_phone,
            'address' => $this->address,
            'email' => $this->email,
            'dni' => $this->dni,
            'ruc' => $this->ruc,
            'agent_type_id' => $this->agent_type_id,
            'agent_type_name' => $this->agent_type_name,
            'is_active' => $this->isActive(),
            'identification_type' => $this->getIdentificationType(),
            'primary_identification' => $this->getPrimaryIdentification(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Incluir tipo de agente si está cargado
            'agent_type' => $this->whenLoaded('agentType', function () {
                return new AgentTypeResource($this->agentType);
            }),
            
            // Incluir transacciones si están cargadas
            'transactions_count' => $this->whenCounted('transactions'),
            'sales_count' => $this->whenCounted('sales'),
            'purchases_count' => $this->whenCounted('purchases'),
        ];
    }

    /**
     * Get identification type
     */
    private function getIdentificationType(): ?string
    {
        if ($this->ruc) return 'RUC';
        if ($this->dni) return 'DNI';
        if ($this->email) return 'EMAIL';
        return null;
    }

    /**
     * Get primary identification
     */
    private function getPrimaryIdentification(): ?string
    {
        return $this->ruc ?? $this->dni ?? $this->email;
    }
}