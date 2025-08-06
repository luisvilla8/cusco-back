<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Transaction\AddPaymentRequest.php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\PaymentMethod;

class AddPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('payment_methods', 'id')->whereNull('deleted_at')
            ],
            'amount_paid' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99'
            ],
            'description' => [ 
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_id.required' => 'El método de pago es obligatorio.',
            'payment_method_id.exists' => 'El método de pago seleccionado no existe o está inactivo.',
            'amount_paid.required' => 'El monto del pago es obligatorio.',
            'amount_paid.numeric' => 'El monto del pago debe ser un número.',
            'amount_paid.min' => 'El monto del pago debe ser mayor a 0.',
            'amount_paid.max' => 'El monto del pago no puede exceder S/ 999,999.99.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.' // ✅ CAMBIO
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_method_id' => 'método de pago',
            'amount_paid' => 'monto del pago',
            'description' => 'descripción' // ✅ CAMBIO
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // ✅ VALIDAR QUE EL PAYMENT METHOD ESTÉ ACTIVO
            $paymentMethodId = $this->input('payment_method_id');
            if ($paymentMethodId) {
                $paymentMethod = PaymentMethod::find($paymentMethodId);
                if (!$paymentMethod || !$paymentMethod->isActive()) {
                    $validator->errors()->add('payment_method_id', 'El método de pago seleccionado está inactivo.');
                }
            }

            // ✅ VALIDAR QUE EL MONTO NO SEA EXCESIVO
            $amountPaid = $this->input('amount_paid');
            if ($amountPaid && $amountPaid > 1000000) {
                $validator->errors()->add('amount_paid', 'El monto del pago es demasiado alto. Contacte al administrador.');
            }
        });
    }

    protected function prepareForValidation()
    {
        // ✅ LIMPIAR Y NORMALIZAR DATOS
        if ($this->has('amount_paid')) {
            $this->merge([
                'amount_paid' => (float) $this->input('amount_paid')
            ]);
        }

        if ($this->has('description')) { // ✅ CAMBIO: notes → description
            $description = trim($this->input('description'));
            $this->merge([
                'description' => empty($description) ? null : $description
            ]);
        }
    }
}