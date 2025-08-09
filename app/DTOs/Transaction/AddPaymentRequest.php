<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Transaction\AddPaymentRequest.php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\PaymentMethod;
use App\Models\Transaction;

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
            'description.max' => 'La descripción no puede exceder 500 caracteres.'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // ✅ VALIDAR QUE EL PAYMENT METHOD ESTÉ ACTIVO
            $paymentMethodId = $this->input('payment_method_id');
            if ($paymentMethodId) {
                try {
                    $paymentMethod = PaymentMethod::active()->find($paymentMethodId);
                    if (!$paymentMethod) {
                        $validator->errors()->add('payment_method_id', 'El método de pago seleccionado no está disponible.');
                        return;
                    }
                } catch (\Exception $e) {
                    \Log::error('Error validating payment method', [
                        'payment_method_id' => $paymentMethodId,
                        'error' => $e->getMessage()
                    ]);
                    $validator->errors()->add('payment_method_id', 'Error al validar el método de pago.');
                    return;
                }
            }

            // ✅ VALIDACIÓN PRINCIPAL: VERIFICAR DEUDA ACTUAL CON getCurrentDebt()
            $transactionId = $this->route('id');
            $requestedPayment = (float) $this->input('amount_paid', 0);
            
            if (!$transactionId) {
                $validator->errors()->add('amount_paid', 'ID de transacción no válido.');
                return;
            }
            
            if ($requestedPayment <= 0) {
                return; // Ya se valida en rules()
            }

            try {
                $transaction = Transaction::with([
                    'transactionDetails.product',
                    'transactionPayments.paymentMethod',
                    'transactionType',
                    'returns' => function($query) {
                        $query->whereNotIn('delivery_status', ['CANCELLED'])
                              ->whereNull('deleted_at');
                    }
                ])->find($transactionId);
                
                if (!$transaction) {
                    $validator->errors()->add('amount_paid', 'Transacción no encontrada.');
                    return;
                }

                // ✅ VERIFICAR QUE LA TRANSACCIÓN PUEDE RECIBIR PAGOS
                if (!$transaction->canReceivePayment()) {
                    $statusMessage = $this->getTransactionStatusMessage($transaction);
                    $validator->errors()->add('amount_paid', "No se pueden agregar pagos: {$statusMessage}");
                    return;
                }
                
                // ✅ USAR getCurrentDebt() PARA OBTENER LA DEUDA REAL
                $currentDebt = $transaction->getCurrentDebt();
                $paymentSurplus = $transaction->getPaymentSurplus();
                
                // ✅ LOGGING DETALLADO PARA DEBUG
                \Log::info('=== PAYMENT VALIDATION - DEBT CHECK ===', [
                    'transaction_id' => $transactionId,
                    'transaction_code' => $transaction->code,
                    'transaction_total' => $transaction->total,
                    'transaction_amount_paid' => $transaction->amount_paid,
                    'current_debt' => $currentDebt,
                    'payment_surplus' => $paymentSurplus,
                    'requested_payment' => $requestedPayment,
                    'is_return' => $transaction->isReturn(),
                    'total_returned' => $transaction->getTotalReturnedAmount(),
                    'total_refunded' => $transaction->getTotalRefundedAmount(),
                    'delivery_status' => $transaction->delivery_status,
                    'payment_status' => $transaction->payment_status
                ]);
                
                // ✅ VALIDAR SEGÚN EL TIPO DE TRANSACCIÓN
                if ($transaction->isReturn()) {
                    // ✅ PARA DEVOLUCIONES: No deberían recibir pagos, solo generar reembolsos
                    $validator->errors()->add('amount_paid', 'No se pueden agregar pagos a devoluciones. Las devoluciones generan reembolsos automáticamente.');
                    return;
                }
                
                // ✅ VALIDAR DEUDA ACTUAL PARA TRANSACCIONES ORIGINALES
                if ($currentDebt <= 0.01) {
                    if ($paymentSurplus > 0) {
                        $formattedSurplus = 'S/ ' . number_format($paymentSurplus, 2);
                        $validator->errors()->add('amount_paid', "Esta transacción ya está completamente pagada y tiene un excedente de {$formattedSurplus}.");
                    } else {
                        $validator->errors()->add('amount_paid', 'Esta transacción ya está completamente pagada.');
                    }
                    return;
                }
                
                // ✅ VALIDAR QUE EL PAGO NO EXCEDA LA DEUDA ACTUAL
                if ($requestedPayment > $currentDebt) {
                    $formattedDebt = 'S/ ' . number_format($currentDebt, 2);
                    $formattedRequested = 'S/ ' . number_format($requestedPayment, 2);
                    $formattedTotal = 'S/ ' . number_format($transaction->total, 2);
                    $formattedPaid = 'S/ ' . number_format($transaction->amount_paid, 2);
                    $formattedReturned = 'S/ ' . number_format($transaction->getTotalReturnedAmount(), 2);
                    
                    $message = "El monto del pago ({$formattedRequested}) excede la deuda actual ({$formattedDebt}). ";
                    $message .= "Total original: {$formattedTotal}, pagado: {$formattedPaid}";
                    
                    if ($transaction->getTotalReturnedAmount() > 0) {
                        $message .= ", devuelto: {$formattedReturned}";
                    }
                    
                    $validator->errors()->add('amount_paid', $message);
                    return;
                }
                
                // ✅ VALIDACIÓN ADICIONAL: VERIFICAR LÍMITES RAZONABLES
                if ($requestedPayment > $transaction->total) {
                    $formattedTotal = 'S/ ' . number_format($transaction->total, 2);
                    $formattedRequested = 'S/ ' . number_format($requestedPayment, 2);
                    $validator->errors()->add('amount_paid', 
                        "El pago ({$formattedRequested}) no puede ser mayor al total original de la transacción ({$formattedTotal})."
                    );
                    return;
                }
                
                \Log::info('=== PAYMENT VALIDATION PASSED ===', [
                    'transaction_id' => $transactionId,
                    'current_debt' => $currentDebt,
                    'requested_payment' => $requestedPayment,
                    'will_remain_debt' => max(0, $currentDebt - $requestedPayment),
                    'validation_result' => 'APPROVED'
                ]);
                
            } catch (\Exception $e) {
                \Log::error('=== ERROR IN PAYMENT VALIDATION ===', [
                    'transaction_id' => $transactionId,
                    'requested_payment' => $requestedPayment,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $validator->errors()->add('amount_paid', 'Error al validar el pago. Por favor, intente nuevamente.');
            }
        });
    }

    // ✅ MÉTODO HELPER: OBTENER MENSAJE DE ESTADO DE TRANSACCIÓN
    private function getTransactionStatusMessage(Transaction $transaction): string
    {
        if ($transaction->isDeliveryCancelled()) {
            return "la transacción está cancelada";
        }
        
        if ($transaction->isPaymentCancelled()) {
            return "los pagos están cancelados";
        }
        
        if ($transaction->isReturn()) {
            return "es una devolución (solo genera reembolsos)";
        }
        
        if ($transaction->isFullyPaid()) {
            return "ya está completamente pagada";
        }
        
        return "estado actual no permite pagos";
    }

    protected function prepareForValidation()
    {
        // ✅ LIMPIAR Y NORMALIZAR DATOS
        if ($this->has('amount_paid')) {
            $amountPaid = $this->input('amount_paid');
            
            // ✅ CONVERTIR STRING A FLOAT Y LIMPIAR
            if (is_string($amountPaid)) {
                $amountPaid = str_replace(['S/', ' ', ','], '', $amountPaid);
            }
            
            $this->merge([
                'amount_paid' => (float) $amountPaid
            ]);
        }

        if ($this->has('description')) {
            $description = trim($this->input('description') ?? '');
            $this->merge([
                'description' => empty($description) ? null : $description
            ]);
        }
    }
}