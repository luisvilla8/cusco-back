<?php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'agent_id' => 'required|integer|exists:agents,id',
            'zone_id' => 'required|integer|exists:zones,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'trip_id' => 'nullable|integer|exists:trips,id',
            'transaction_type_id' => 'required|integer|exists:transaction_types,id',
            'description' => 'nullable|string|max:1000',
            'date' => 'required|date|before_or_equal:today',
            'delivery_status' => 'nullable|in:PENDING,DELIVERED,RETURNED,CANCELLED',
            'payment_status' => 'nullable|in:PENDING,PARTIAL,PAID,CANCELLED',
            
            // ✅ relation_to NO DEBE ESTAR AQUÍ (SOLO PARA DEVOLUCIONES)
            // 'relation_to' => 'nullable|integer|exists:transactions,id',
            
            // ✅ DETALLES DE PRODUCTOS
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|integer|exists:products,id',
            'details.*.price' => 'required|numeric|min:0.01',
            'details.*.quantity' => 'required|numeric|min:0.01',
            
            // ✅ PAGOS CON DESCRIPTION OPCIONAL
            'payments' => 'nullable|array',
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
            'transaction_type_id.required' => 'El tipo de transacción es obligatorio.',
            'transaction_type_id.exists' => 'El tipo de transacción seleccionado no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            
            'details.required' => 'Debe incluir al menos un producto.',
            'details.array' => 'Los detalles deben ser un arreglo.',
            'details.min' => 'Debe incluir al menos un producto.',
            
            'details.*.product_id.required' => 'El producto es obligatorio.',
            'details.*.product_id.exists' => 'El producto seleccionado no existe.',
            'details.*.price.required' => 'El precio es obligatorio.',
            'details.*.price.numeric' => 'El precio debe ser un número.',
            'details.*.price.min' => 'El precio debe ser mayor a 0.',
            'details.*.quantity.required' => 'La cantidad es obligatoria.',
            'details.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'details.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            
            'payments.array' => 'Los pagos deben ser un arreglo.',
            'payments.*.payment_method_id.required_with' => 'El método de pago es obligatorio.',
            'payments.*.payment_method_id.exists' => 'El método de pago seleccionado no existe.',
            'payments.*.amount_paid.required_with' => 'El monto del pago es obligatorio.',
            'payments.*.amount_paid.numeric' => 'El monto debe ser un número.',
            'payments.*.amount_paid.min' => 'El monto debe ser mayor a 0.',
            
            'description.max' => 'La descripción de la transacción no puede exceder 1000 caracteres.',
            
            'payments.*.description.max' => 'La descripción del pago no puede exceder 500 caracteres.',
            
            'delivery_status.in' => 'El estado de entrega debe ser: PENDING, DELIVERED, RETURNED o CANCELLED.',
            'payment_status.in' => 'El estado de pago debe ser: PENDING, PARTIAL, PAID o CANCELLED.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            
            // ✅ OBTENER TIPO DE TRANSACCIÓN PARA VALIDACIONES ESPECÍFICAS
            $transactionTypeId = $this->input('transaction_type_id');
            $transactionType = null;
            
            if ($transactionTypeId) {
                $transactionType = \App\Models\TransactionType::find($transactionTypeId);
            }
            
            $isReturn = $transactionType && in_array(
                strtoupper($transactionType->code), 
                ['RETURN_SALE', 'RETURN_PURCHASE']
            );
            
            // ✅ VALIDACIÓN DE ZONA PARA VENDEDORES
            if ($user->hasRole('Vendedor')) {
                $zoneId = $this->input('zone_id');
                
                if (!$zoneId) {
                    $validator->errors()->add('zone_id', 'Los vendedores deben especificar una zona.');
                    return;
                }
                
                if (!$user->hasZone($zoneId)) {
                    $zone = \App\Models\Zone::find($zoneId);
                    $zoneName = $zone ? $zone->name : "ID {$zoneId}";
                    $validator->errors()->add('zone_id', "No tienes permisos para operar en la zona: {$zoneName}");
                    return;
                }
            }
            
            // ✅ VALIDACIÓN AGENT_ID SEGÚN TRANSACTION_TYPE_ID
            $agentId = $this->input('agent_id');
            
            if ($agentId && $transactionType) {
                try {
                    $agent = \App\Models\Agent::with('agentType')->find($agentId);
                    
                    if (!$agent) {
                        $validator->errors()->add('agent_id', 'El agente seleccionado no existe.');
                        return;
                    }
                    
                    // ✅ VALIDAR SEGÚN EL TIPO DE TRANSACCIÓN
                    $transactionCode = strtoupper($transactionType->code);
                    $agentTypeName = strtolower($agent->agentType?->name ?? '');
                    
                    if ($transactionCode === 'SALE') {
                        if (!str_contains($agentTypeName, 'cliente')) {
                            $validator->errors()->add('agent_id', 
                                'Para ventas, el agente debe ser un cliente.'
                            );
                        }
                    } elseif ($transactionCode === 'PURCHASE') {
                        if (!str_contains($agentTypeName, 'proveedor')) {
                            $validator->errors()->add('agent_id', 
                                'Para compras, el agente debe ser un proveedor.'
                            );
                        }
                    } elseif (in_array($transactionCode, ['RETURN_SALE', 'RETURN_PURCHASE'])) {
                        // ✅ DEVOLUCIONES: Validar contra transacción original
                        $relationTo = $this->input('relation_to');
                        if ($relationTo) {
                            $originalTransaction = \App\Models\Transaction::find($relationTo);
                            if ($originalTransaction && $originalTransaction->agent_id !== $agentId) {
                                $validator->errors()->add('agent_id', 
                                    'El agente debe ser el mismo de la transacción original.'
                                );
                            }
                        }
                    }
                    
                } catch (\Exception $e) {
                    \Log::error("Error validating agent type", [
                        'agent_id' => $agentId,
                        'transaction_type_id' => $transactionTypeId,
                        'error' => $e->getMessage()
                    ]);
                    
                    $validator->errors()->add('agent_id', 'Error al validar el tipo de agente.');
                }
            }
            
            // ✅ VALIDAR PAYMENT METHODS ACTIVOS
            $payments = $this->input('payments', []);
            if (!empty($payments)) {
                foreach ($payments as $index => $payment) {
                    $paymentMethodId = $payment['payment_method_id'] ?? null;
                    
                    if (!$paymentMethodId) {
                        continue;
                    }
                    
                    try {
                        $paymentMethod = \App\Models\PaymentMethod::active()->find($paymentMethodId);
                        
                        if (!$paymentMethod) {
                            $validator->errors()->add(
                                "payments.{$index}.payment_method_id", 
                                "El método de pago está inactivo o no existe"
                            );
                            continue;
                        }
                        
                    } catch (\Exception $e) {
                        \Log::error("Error validating payment method", [
                            'payment_method_id' => $paymentMethodId,
                            'error' => $e->getMessage()
                        ]);
                        
                        $validator->errors()->add(
                            "payments.{$index}.payment_method_id", 
                            "Error al validar el método de pago"
                        );
                    }
                }
            }
            
            // ✅ VALIDAR PRECIOS SOLO PARA TRANSACCIONES ORIGINALES (NO DEVOLUCIONES)
            if (!$isReturn) {
                $details = $this->input('details', []);
                $zoneId = $this->input('zone_id');
                
                if (!empty($details) && $zoneId) {
                    foreach ($details as $index => $detail) {
                        $productId = $detail['product_id'] ?? null;
                        
                        if (!$productId) {
                            continue;
                        }
                        
                        try {
                            $product = \App\Models\Product::active()->find($productId);
                            
                            if (!$product) {
                                $validator->errors()->add(
                                    "details.{$index}.product_id", 
                                    "El producto no existe o está inactivo"
                                );
                                continue;
                            }
                            
                            // ✅ OBTENER PRECIO SEGÚN LA ZONA
                            $currentPrice = $this->getProductPriceForZone($productId, $zoneId);
                            $sentPrice = (float) ($detail['price'] ?? 0);
                            
                            // ✅ COMPARAR PRECIO ENVIADO CON PRECIO ACTUAL
                            if (abs($sentPrice - $currentPrice) > 0.01) {
                                $priceSource = $this->getPriceSource($productId, $zoneId);
                                $validator->errors()->add(
                                    "details.{$index}.price", 
                                    "El precio del producto '{$product->name}' ha cambiado. Precio actual: S/ {$currentPrice} ({$priceSource}), precio enviado: S/ {$sentPrice}. Por favor actualiza los precios."
                                );
                            }
                            
                            // ✅ VALIDAR STOCK DISPONIBLE (SOLO PARA VENTAS)
                            if ($transactionCode === 'SALE') {
                                $quantity = (float) ($detail['quantity'] ?? 0);
                                if (!$product->hasStock($quantity)) {
                                    $validator->errors()->add(
                                        "details.{$index}.quantity", 
                                        "Stock insuficiente para {$product->name}. Stock disponible: {$product->stock}, cantidad solicitada: {$quantity}"
                                    );
                                }
                            }
                            
                        } catch (\Exception $e) {
                            \Log::error("Error validating product", [
                                'product_id' => $productId,
                                'error' => $e->getMessage()
                            ]);
                            
                            $validator->errors()->add(
                                "details.{$index}.product_id", 
                                "Error al validar el producto"
                            );
                        }
                    }
                }
            } else {
                // ✅ PARA DEVOLUCIONES: VALIDAR SOLO QUE LOS PRODUCTOS EXISTAN
                $details = $this->input('details', []);
                foreach ($details as $index => $detail) {
                    $productId = $detail['product_id'] ?? null;
                    
                    if ($productId) {
                        $product = \App\Models\Product::active()->find($productId);
                        if (!$product) {
                            $validator->errors()->add(
                                "details.{$index}.product_id", 
                                "El producto no existe o está inactivo"
                            );
                        }
                    }
                }
            }
            
            // ✅ VALIDAR QUE LA SUMA DE PAGOS NO EXCEDA EL TOTAL
            if (!empty($details) && !empty($payments)) {
                try {
                    $total = collect($details)->sum(fn($detail) => ($detail['price'] ?? 0) * ($detail['quantity'] ?? 0));
                    $totalPaid = collect($payments)->sum(fn($payment) => $payment['amount_paid'] ?? 0);
                    
                    if ($totalPaid > $total) {
                        $validator->errors()->add('payments', 'La suma de los pagos no puede exceder el total de la transacción.');
                    }
                } catch (\Exception $e) {
                    \Log::error("Error validating payment total", [
                        'error' => $e->getMessage(),
                        'details' => $details,
                        'payments' => $payments
                    ]);
                }
            }
        });
    }

    // ✅ NUEVO MÉTODO PRIVADO: Obtener precio del producto según la zona
    private function getProductPriceForZone(int $productId, int $zoneId): float
    {
        // 1. Buscar precio específico para la zona
        $zonePriceDetail = \App\Models\ProductPriceDetail::active()
            ->where('product_id', $productId)
            ->where('zone_id', $zoneId)
            ->first();
        
        if ($zonePriceDetail) {
            \Log::info("Using zone-specific price", [
                'product_id' => $productId,
                'zone_id' => $zoneId,
                'zone_price' => $zonePriceDetail->price
            ]);
            return (float) $zonePriceDetail->price;
        }
        
        // 2. Si no hay precio para la zona, usar precio base del producto
        $product = \App\Models\Product::find($productId);
        $basePrice = $product ? (float) $product->price : 0;
        
        \Log::info("Using base product price", [
            'product_id' => $productId,
            'zone_id' => $zoneId,
            'base_price' => $basePrice,
            'reason' => 'No zone-specific price found'
        ]);
        
        return $basePrice;
    }

    // ✅ NUEVO MÉTODO PRIVADO: Obtener fuente del precio para mensajes
    private function getPriceSource(int $productId, int $zoneId): string
    {
        $zonePriceDetail = \App\Models\ProductPriceDetail::active()
            ->where('product_id', $productId)
            ->where('zone_id', $zoneId)
            ->first();
        
        if ($zonePriceDetail) {
            $zone = \App\Models\Zone::find($zoneId);
            return "precio para zona {$zone?->name}";
        }
        
        return "precio base";
    }
}