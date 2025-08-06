<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\TransactionType\IndexTransactionTypeRequest.php

namespace App\Http\Requests\Api\V1\TransactionType;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:name,code,created_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }
}