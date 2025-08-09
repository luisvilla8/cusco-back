<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Http\Requests\Api\V1\Transaction\StoreReturnRequest.php

namespace App\Http\Requests\Api\V1\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
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
            'relation_to' => 'required|integer|exists:transactions,id',
            'date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:255',

            //  DETALLES DE PRODUCTOS A DEVOLVER
            'details' => 'required|array|min:1',
            'details.*.product_id' => 'required|integer|exists:products,id',
            'details.*.price' => 'required|numeric|min:0.01',
            'details.*.quantity' => 'required|numeric|min:0.01',

            //  PAGOS OPCIONALES - SOLO REQUERIDOS SI HAY REEMBOLSO
            'payments' => 'nullable|array',
            'payments.*.payment_method_id' => 'required_with:payments|integer|exists:payment_methods,id,deleted_at,NULL',
            'payments.*.description' => 'nullable|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'relation_to.required' => 'La transacción original es obligatoria.',
            'relation_to.exists' => 'La transacción original no existe.',
            'date.required' => 'La fecha es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',

            'details.required' => 'Debe incluir al menos un producto a devolver.',
            'details.array' => 'Los detalles deben ser un arreglo.',
            'details.min' => 'Debe incluir al menos un producto a devolver.',

            'details.*.product_id.required' => 'El producto es obligatorio.',
            'details.*.product_id.exists' => 'El producto seleccionado no existe.',
            'details.*.price.required' => 'El precio es obligatorio.',
            'details.*.price.numeric' => 'El precio debe ser un número.',
            'details.*.price.min' => 'El precio debe ser mayor a 0.',
            'details.*.quantity.required' => 'La cantidad es obligatoria.',
            'details.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'details.*.quantity.min' => 'La cantidad debe ser mayor a 0.',

            //  MENSAJES MEJORADOS PARA PAYMENTS
            'payments.*.payment_method_id.required_with' => 'El método de pago es obligatorio cuando se especifica un reembolso.',
            'payments.*.payment_method_id.exists' => 'El método de pago seleccionado no existe o está inactivo.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $relationTo = $this->input('relation_to');
            $details = $this->input('details', []);

            if (!$relationTo || empty($details)) {
                return;
            }

            try {
                //  OBTENER TRANSACCIÓN ORIGINAL
                $originalTransaction = \App\Models\Transaction::with([
                    'transactionDetails.product',
                    'returns.transactionDetails',
                    'transactionType'
                ])->find($relationTo);

                if (!$originalTransaction) {
                    $validator->errors()->add('relation_to', 'Transacción original no encontrada.');
                    return;
                }

                //  VALIDACIÓN: NO PERMITIR DEVOLVER DEVOLUCIONES
                if ($originalTransaction->isReturn()) {
                    $validator->errors()->add('relation_to', 'No se pueden crear devoluciones de una devolución. Solo se pueden devolver transacciones originales (ventas o compras).');
                    return;
                }

                //  VALIDAR QUE SE PUEDA DEVOLVER
                if (!$originalTransaction->canBeReturned()) {
                    $reason = $originalTransaction->isDeliveryPending() ?
                        'La transacción debe estar entregada para poder ser devuelta.' :
                        'Esta transacción no puede ser devuelta en su estado actual.';

                    $validator->errors()->add('relation_to', $reason);
                    return;
                }

                //  VALIDAR QUE ES TRANSACCIÓN ORIGINAL
                if (!$originalTransaction->isOriginalTransaction()) {
                    $validator->errors()->add('relation_to', 'Solo se pueden devolver ventas o compras originales.');
                    return;
                }

                //  OBTENER PRECIOS ORIGINALES
                $originalPrices = $originalTransaction->transactionDetails()
                    ->get()
                    ->mapWithKeys(function ($detail) {
                        return [$detail->product_id => [
                            'price' => (float) $detail->price,
                            'quantity' => (float) $detail->quantity
                        ]];
                    })
                    ->toArray();

                //  OBTENER CANTIDADES YA DEVUELTAS (SOLO DEVOLUCIONES ACTIVAS - NO CANCELADAS)
                $returnedQuantities = [];
                foreach ($originalTransaction->returns as $return) {
                    //  SOLO CONTAR DEVOLUCIONES ACTIVAS
                    if (
                        in_array($return->delivery_status, ['DELIVERED', 'RETURNED']) &&
                        $return->delivery_status !== 'CANCELLED' &&
                        $return->payment_status !== 'CANCELLED'
                    ) {

                        foreach ($return->transactionDetails as $returnDetail) {
                            $productId = $returnDetail->product_id;
                            $returnedQuantities[$productId] = ($returnedQuantities[$productId] ?? 0) + abs($returnDetail->quantity);
                        }
                    }
                }

                //  VALIDAR CADA DETALLE DE LA DEVOLUCIÓN
                foreach ($details as $index => $detail) {
                    $productId = $detail['product_id'] ?? null;
                    $sentPrice = (float) ($detail['price'] ?? 0);
                    $sentQuantity = (float) ($detail['quantity'] ?? 0);

                    if (!$productId) continue;

                    //  VALIDAR QUE EL PRODUCTO ESTÉ EN LA TRANSACCIÓN ORIGINAL
                    if (!isset($originalPrices[$productId])) {
                        $validator->errors()->add(
                            "details.{$index}.product_id",
                            "Este producto no está en la transacción original."
                        );
                        continue;
                    }

                    $originalPrice = $originalPrices[$productId]['price'];
                    $originalQuantity = $originalPrices[$productId]['quantity'];
                    $alreadyReturned = $returnedQuantities[$productId] ?? 0;
                    $availableToReturn = $originalQuantity - $alreadyReturned;

                    //  VALIDAR PRECIO ORIGINAL
                    if (abs($sentPrice - $originalPrice) > 0.01) {
                        $validator->errors()->add(
                            "details.{$index}.price",
                            "El precio debe ser el precio original: S/ " . number_format($originalPrice, 2) .
                                ". Precio enviado: S/ " . number_format($sentPrice, 2)
                        );
                    }

                    //  VALIDAR CANTIDAD DISPONIBLE PARA DEVOLVER
                    if ($sentQuantity > $availableToReturn) {
                        $product = \App\Models\Product::find($productId);
                        $productName = $product ? $product->name : "ID {$productId}";

                        $validator->errors()->add(
                            "details.{$index}.quantity",
                            "No se puede devolver {$sentQuantity} de '{$productName}'. " .
                                "Cantidad original: {$originalQuantity}, ya devuelto: {$alreadyReturned}, " .
                                "disponible para devolver: {$availableToReturn}"
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Error validating return request", [
                    'relation_to' => $relationTo,
                    'details' => $details,
                    'error' => $e->getMessage()
                ]);

                $validator->errors()->add('relation_to', 'Error al validar la devolución.');
            }
        });
    }
}
