<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     *  REGLAS COMUNES: Paginación y búsqueda
     */
    public function rules(): array
    {
        return array_merge($this->getCommonRules(), $this->getSpecificRules());
    }

    /**
     *  REGLAS COMPARTIDAS: Para todos los índices
     */
    protected function getCommonRules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_order' => 'in:asc,desc',
            'search' => 'nullable|string|max:255',
        ];
    }

    /**
     *  ABSTRACT: Cada hijo define sus campos específicos
     */
    abstract protected function getSpecificRules(): array;

    /**
     *  MENSAJES COMUNES
     */
    public function messages(): array
    {
        return array_merge($this->getCommonMessages(), $this->getSpecificMessages());
    }

    /**
     *  MENSAJES COMPARTIDOS
     */
    protected function getCommonMessages(): array
    {
        return [
            'page.integer' => 'El número de página debe ser un entero.',
            'page.min' => 'El número de página debe ser al menos 1.',
            'per_page.integer' => 'El parámetro per_page debe ser un entero.',
            'per_page.min' => 'El parámetro per_page debe ser al menos 1.',
            'per_page.max' => 'No se pueden mostrar más de 100 elementos por página.',
            'sort_order.in' => 'El orden debe ser: asc o desc.',
            'search.string' => 'El parámetro search debe ser una cadena de texto.',
            'search.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
        ];
    }

    /**
     *  ABSTRACT: Cada hijo define sus mensajes específicos
     */
    protected function getSpecificMessages(): array
    {
        return [];
    }

    /**
     *  HELPER: Obtener campos de ordenamiento permitidos
     */
    abstract protected function getAllowedSortFields(): array;

    /**
     *  HELPER: Validar campo de ordenamiento
     */
    protected function getSortByRule(): string
    {
        $allowedFields = implode(',', $this->getAllowedSortFields());
        return "nullable|string|in:{$allowedFields}";
    }
}