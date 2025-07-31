<?php

namespace App\Http\Requests\Api\V1\MeasureType;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexMeasureTypeRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de unidades de medida
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
        ];
    }

    /**
     * Campos de ordenamiento para unidades de medida
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'symbol', 'created_at', 'updated_at'];  // ✅ CAMBIO: description, acronym → name, symbol
    }

    /**
     * Mensajes específicos de unidades de medida
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, symbol, created_at o updated_at.',  // ✅ CAMBIO
        ];
    }
}