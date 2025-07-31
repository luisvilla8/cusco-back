<?php

namespace App\Http\Requests\Api\V1\ProductCategory;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexProductCategoryRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de categorías de productos
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
        ];
    }

    /**
     * Campos de ordenamiento para categorías de productos
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'description', 'created_at', 'updated_at'];
    }

    /**
     * Mensajes específicos de categorías de productos
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, description, created_at o updated_at.',
        ];
    }
}