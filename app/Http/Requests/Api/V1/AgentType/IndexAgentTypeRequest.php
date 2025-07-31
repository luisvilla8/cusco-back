<?php

namespace App\Http\Requests\Api\V1\AgentType;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexAgentTypeRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de tipos de agente
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
        ];
    }

    /**
     * Campos de ordenamiento para tipos de agente
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'description', 'created_at', 'updated_at'];
    }

    /**
     * Mensajes específicos de tipos de agente
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, description, created_at o updated_at.',
        ];
    }
}