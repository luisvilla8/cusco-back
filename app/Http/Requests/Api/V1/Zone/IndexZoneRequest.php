<?php

namespace App\Http\Requests\Api\V1\Zone;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexZoneRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de zonas
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
        ];
    }

    /**
     * Campos de ordenamiento para zonas
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'description', 'usage', 'created_at', 'updated_at'];
    }

    /**
     * Mensajes específicos de zonas
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, description, usage, created_at o updated_at.',
        ];
    }
}