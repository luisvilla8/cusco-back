<?php

namespace App\Http\Requests\Api\V1\Role;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexRoleRequest extends BaseIndexRequest
{
    /**
     * ✅ ESPECÍFICO: Solo reglas específicas de roles
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
        ];
    }

    /**
     * ✅ ESPECÍFICO: Campos de ordenamiento para roles
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'description', 'created_at', 'updated_at'];
    }

    /**
     * ✅ ESPECÍFICO: Mensajes específicos de roles
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, description, created_at o updated_at.',
        ];
    }
}