<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Transaction\UpdateTransactionRequest.php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;
        
        return $user->hasAnyRole(['Administrador', 'Super Admin', 'Vendedor']);
    }

    public function rules(): array
    {
        return [
            'agent_id' => 'sometimes|required|integer|exists:agents,id',
            'zone_id' => 'sometimes|required|integer|exists:zones,id',
            'description' => 'nullable|string|max:1000',
            'date' => 'sometimes|required|date|before_or_equal:today',
            
            // ✅ DETALLES DE PRODUCTOS (OPCIONALES PARA UPDATE)
            'details' => 'sometimes|array|min:1',
            'details.*.product_id' => 'required_with:details|integer|exists:products,id',
            'details.*.price' => 'required_with:details|numeric|min:0.01',
            'details.*.quantity' => 'required_with:details|numeric|min:0.01',
            
            // ✅ PAGOS (OPCIONALES PARA UPDATE)
            'payments' => 'sometimes|array',
            'payments.*.payment_method_id' => 'required_with:payments|integer|exists:payment_methods,id',
            'payments.*.amount_paid' => 'required_with:payments|numeric|min:0.01',
            'payments.*.description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.required' => 'El cliente es obligatorio.',
            'agent_id.exists' => 'El cliente seleccionado no existe.',
            'zone_id.required' => 'La zona es obligatoria.',
            'zone_id.exists' => 'La zona seleccionada no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            
            'details.array' => 'Los detalles deben ser un arreglo.',
            'details.min' => 'Debe incluir al menos un producto.',
            
            'details.*.product_id.required_with' => 'El producto es obligatorio.',
            'details.*.product_id.exists' => 'El producto seleccionado no existe.',
            'details.*.price.required_with' => 'El precio es obligatorio.',
            'details.*.price.numeric' => 'El precio debe ser un número.',
            'details.*.price.min' => 'El precio debe ser mayor a 0.',
            'details.*.quantity.required_with' => 'La cantidad es obligatoria.',
            'details.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'details.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // ✅ VALIDAR AGENT_ID SEGÚN TRANSACTION_TYPE_ID TAMBIÉN EN UPDATE
            $agentId = $this->input('agent_id');
            $transactionTypeId = $this->input('transaction_type_id');
            
            if ($agentId && $transactionTypeId) {
                try {
                    $agent = \App\Models\Agent::with('agentType')->find($agentId);
                    $transactionType = \App\Models\TransactionType::find($transactionTypeId);
                    
                    if (!$agent || !$transactionType) {
                        return; // Validación básica de existencia ya maneja esto
                    }
                    
                    $transactionCode = strtoupper($transactionType->code);
                    $agentTypeName = strtolower($agent->agentType?->name ?? '');
                    
                    if ($transactionCode === 'SALE') {
                        if (!str_contains($agentTypeName, 'cliente')) {
                            $validator->errors()->add('agent_id', 
                                "Para ventas, debe seleccionar un cliente. El agente '{$agent->name}' es de tipo '{$agent->agentType?->name}'."
                            );
                        }
                    } elseif ($transactionCode === 'PURCHASE') {
                        if (!str_contains($agentTypeName, 'proveedor')) {
                            $validator->errors()->add('agent_id', 
                                "Para compras, debe seleccionar un proveedor. El agente '{$agent->name}' es de tipo '{$agent->agentType?->name}'."
                            );
                        }
                    }
                    
                } catch (\Exception $e) {
                    \Log::error("Error validating agent type in update", [
                        'agent_id' => $agentId,
                        'transaction_type_id' => $transactionTypeId,
                        'error' => $e->getMessage()
                    ]);
                    
                    $validator->errors()->add('agent_id', 'Error al validar el tipo de agente.');
                }
            }
            
            $user = $this->user();
            
            // ✅ VALIDACIÓN DE ZONA PARA VENDEDORES (SOLO SI SE ESTÁ ACTUALIZANDO LA ZONA)
            if ($user->hasRole('Vendedor') && $this->has('zone_id')) {
                $zoneId = $this->input('zone_id');
                
                if ($zoneId && !$user->hasZone($zoneId)) {
                    $zone = \App\Models\Zone::find($zoneId);
                    $zoneName = $zone ? $zone->name : "ID {$zoneId}";
                    $validator->errors()->add('zone_id', "No tienes permisos para cambiar a la zona: {$zoneName}");
                }
            }
            
            // ✅ VALIDAR PAYMENT METHODS ACTIVOS (SI SE ESTÁN ACTUALIZANDO)
            if ($this->has('payments')) {
                $payments = $this->input('payments', []);
                
                foreach ($payments as $index => $payment) {
                    $paymentMethodId = $payment['payment_method_id'] ?? null;
                    
                    if ($paymentMethodId) {
                        $paymentMethod = \App\Models\PaymentMethod::active()->find($paymentMethodId);
                        
                        if (!$paymentMethod) {
                            $validator->errors()->add(
                                "payments.{$index}.payment_method_id", 
                                "El método de pago está inactivo o no existe"
                            );
                        }
                    }
                }
            }
            
            // ✅ VALIDAR PRECIOS ACTUALIZADOS
            if ($this->has('details')) {
                $details = $this->input('details', []);
                $zoneId = $this->input('zone_id');
                
                // Si no se proporciona zona en la actualización, obtenerla de la transacción existente
                if (!$zoneId) {
                    $transactionId = $this->route('id');
                    $transaction = \App\Models\Transaction::find($transactionId);
                    $zoneId = $transaction?->zone_id;
                }
                
                if (!empty($details) && $zoneId) {
                    foreach ($details as $index => $detail) {
                        $productId = $detail['product_id'] ?? null;
                        
                        if ($productId) {
                            $product = \App\Models\Product::active()->find($productId);
                            
                            if ($product) {
                                // Obtener precio actual según la zona
                                $currentPrice = $this->getProductPriceForZone($productId, $zoneId);
                                $sentPrice = (float) ($detail['price'] ?? 0);
                                
                                if (abs($sentPrice - $currentPrice) > 0.01) {
                                    $validator->errors()->add(
                                        "details.{$index}.price", 
                                        "El precio del producto '{$product->name}' ha cambiado. Precio actual: S/ {$currentPrice}, precio enviado: S/ {$sentPrice}."
                                    );
                                }
                                
                                // Validar stock
                                $quantity = (float) ($detail['quantity'] ?? 0);
                                if (!$product->hasStock($quantity)) {
                                    $validator->errors()->add(
                                        "details.{$index}.quantity", 
                                        "Stock insuficiente para {$product->name}. Stock disponible: {$product->stock}"
                                    );
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    // ✅ MÉTODOS HELPER PARA PRECIOS
    private function getProductPriceForZone(int $productId, int $zoneId): float
    {
        $zonePriceDetail = \App\Models\ProductPriceDetail::active()
            ->where('product_id', $productId)
            ->where('zone_id', $zoneId)
            ->first();
        
        if ($zonePriceDetail) {
            return (float) $zonePriceDetail->price;
        }
        
        $product = \App\Models\Product::find($productId);
        return $product ? (float) $product->price : 0;
    }
}