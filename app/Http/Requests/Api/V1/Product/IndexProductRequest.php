<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Product\IndexProductRequest.php

namespace App\Http\Requests\Api\V1\Product;

use App\Http\Requests\Api\V1\BaseIndexRequest;

class IndexProductRequest extends BaseIndexRequest
{
    /**
     * Reglas específicas de productos
     */
    protected function getSpecificRules(): array
    {
        return [
            'sort_by' => $this->getSortByRule(),
            'product_category_id' => 'nullable|integer|exists:product_categories,id',
            'measure_type_id' => 'nullable|integer|exists:measure_types,id',
            'stock_status' => 'nullable|string|in:SIN_STOCK,STOCK_BAJO,STOCK_NORMAL,STOCK_ALTO',
        ];
    }

    /**
     * Campos de ordenamiento para productos
     */
    protected function getAllowedSortFields(): array
    {
        return ['name', 'code', 'stock', 'min_stock', 'max_stock', 'created_at', 'updated_at'];
    }

    /**
     * Mensajes específicos de productos
     */
    protected function getSpecificMessages(): array
    {
        return [
            'sort_by.in' => 'El campo de ordenamiento debe ser: name, code, stock, min_stock, max_stock, created_at o updated_at.',
            'product_category_id.exists' => 'La categoría de producto seleccionada no existe.',
            'measure_type_id.exists' => 'El tipo de medida seleccionado no existe.',
            'stock_status.in' => 'El estado de stock debe ser: SIN_STOCK, STOCK_BAJO, STOCK_NORMAL o STOCK_ALTO.',
        ];
    }
}