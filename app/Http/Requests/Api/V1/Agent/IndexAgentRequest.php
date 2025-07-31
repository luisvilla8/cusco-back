<?php

namespace App\Http\Requests\Api\V1\Agent;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexAgentRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de agentes
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
            'agent_type_id' => 'nullable|integer|exists:agent_types,id',
        ];
    }

    /**
     * Campos de ordenamiento para agentes
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'email', 'dni', 'ruc', 'created_at', 'updated_at'];
    }

    /**
     * Mensajes específicos de agentes
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, email, dni, ruc, created_at o updated_at.',
            'agent_type_id.exists' => 'El tipo de agente seleccionado no existe.',
        ];
    }
}