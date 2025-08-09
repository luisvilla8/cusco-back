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
            
            //  DETALLES DE PRODUCTOS (OPCIONALES PARA UPDATE)
            'details' => 'sometimes|array|min:1',
            'details.*.product_id' => 'required_with:details|integer|exists:products,id',
            'details.*.price' => 'required_with:details|numeric|min:0.01',
            'details.*.quantity' => 'required_with:details|numeric|min:0.01',
            
            //  PAGOS (OPCIONALES PARA UPDATE)
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
            //  OBTENER ID DE LA TRANSACCIÓN QUE SE ESTÁ ACTUALIZANDO
            $transactionId = $this->route('id');
            
            //  VALIDAR PRECIOS ACTUALIZADOS Y STOCK CORREGIDO
            if ($this->has('details')) {
                $details = $this->input('details', []);
                $zoneId = $this->input('zone_id');
                
                // Si no se proporciona zona en la actualización, obtenerla de la transacción existente
                if (!$zoneId) {
                    $transaction = \App\Models\Transaction::find($transactionId);
                    $zoneId = $transaction?->zone_id;
                }
                
                //  OBTENER TRANSACCIÓN ACTUAL PARA VERIFICAR TIPO Y DETALLES EXISTENTES
                $currentTransaction = null;
                $oldDetails = [];
                
                if ($transactionId) {
                    $currentTransaction = \App\Models\Transaction::with(['transactionDetails.product', 'transactionType'])->find($transactionId);
                    
                    if ($currentTransaction) {
                        //  OBTENER DETALLES ACTUALES AGRUPADOS POR PRODUCTO
                        foreach ($currentTransaction->transactionDetails as $oldDetail) {
                            $productId = $oldDetail->product_id;
                            $oldDetails[$productId] = ($oldDetails[$productId] ?? 0) + $oldDetail->quantity;
                        }
                    }
                }
                
                if (!empty($details) && $zoneId && $currentTransaction) {
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
                                
                                //  VALIDAR STOCK CORREGIDO - SOLO PARA VENTAS PENDING
                                if ($currentTransaction->isSale() && $currentTransaction->isDeliveryPending()) {
                                    $newQuantity = (float) ($detail['quantity'] ?? 0);
                                    $oldQuantityForThisProduct = $oldDetails[$productId] ?? 0;
                                    
                                    //  CALCULAR STOCK DISPONIBLE CONSIDERANDO LA LIBERACIÓN DE LA RESERVA ACTUAL
                                    $currentReservedStock = $product->reserved_stock;
                                    $stockAfterRelease = $product->stock - ($currentReservedStock - $oldQuantityForThisProduct);
                                    $availableStock = $stockAfterRelease;
                                    
                                    \Log::info('Stock validation for update', [
                                        'product_id' => $productId,
                                        'product_name' => $product->name,
                                        'transaction_id' => $transactionId,
                                        'total_stock' => $product->stock,
                                        'current_reserved_stock' => $currentReservedStock,
                                        'old_quantity_for_product' => $oldQuantityForThisProduct,
                                        'new_quantity' => $newQuantity,
                                        'stock_after_release' => $stockAfterRelease,
                                        'available_stock' => $availableStock,
                                        'calculation' => "stock({$product->stock}) - (reserved({$currentReservedStock}) - old_quantity({$oldQuantityForThisProduct})) = {$availableStock}"
                                    ]);
                                    
                                    if ($availableStock < $newQuantity) {
                                        $validator->errors()->add(
                                            "details.{$index}.quantity", 
                                            "Stock disponible insuficiente para {$product->name}. " .
                                            "Stock total: {$product->stock}, reservado actual: {$currentReservedStock}, " .
                                            "cantidad actual del producto: {$oldQuantityForThisProduct}, " .
                                            "disponible después de liberar reserva: {$availableStock}, " .
                                            "cantidad solicitada: {$newQuantity}"
                                        );
                                    } else {
                                        \Log::info('Stock validation passed', [
                                            'product_id' => $productId,
                                            'available_stock' => $availableStock,
                                            'requested_quantity' => $newQuantity
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            //  RESTO DE VALIDACIONES EXISTENTES...
            $user = $this->user();
            
            //  VALIDACIÓN DE ZONA PARA VENDEDORES (SOLO SI SE ESTÁ ACTUALIZANDO LA ZONA)
            if ($user->hasRole('Vendedor') && $this->has('zone_id')) {
                $zoneId = $this->input('zone_id');
                
                if ($zoneId && !$user->hasZone($zoneId)) {
                    $zone = \App\Models\Zone::find($zoneId);
                    $zoneName = $zone ? $zone->name : "ID {$zoneId}";
                    $validator->errors()->add('zone_id', "No tienes permisos para cambiar a la zona: {$zoneName}");
                }
            }
            
            //  VALIDAR PAYMENT METHODS ACTIVOS (SI SE ESTÁN ACTUALIZANDO)
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
        });
    }

    //  MÉTODOS HELPER PARA PRECIOS
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