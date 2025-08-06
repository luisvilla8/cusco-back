<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Transaction\IndexTransactionRequest.php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['Administrador', 'Super Admin', 'Vendedor']);
    }

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'zone_id' => 'nullable|integer|exists:zones,id',
            'trip_id' => 'nullable|integer|exists:trips,id',
            'delivery_status' => 'nullable|in:PENDING,DELIVERED,RETURNED,CANCELLED',
            'payment_status' => 'nullable|in:PENDING,PARTIAL,PAID,CANCELLED',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|in:date,total,created_at',
            'sort_direction' => 'nullable|in:asc,desc'
        ];
    }
}