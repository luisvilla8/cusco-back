<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Mappers\TransactionMapper.php

namespace App\Mappers;

use App\DTOs\Transaction\{TransactionDTO, TransactionDropdownDTO};
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionMapper
{
    public static function modelToDTO(Transaction $transaction): TransactionDTO
    {
        // ✅ PARA DEVOLUCIONES: NO CALCULAR DEUDA PROPIA, USAR INFO DE LA ORIGINAL
        if ($transaction->isReturn()) {
            $originalTransaction = $transaction->originalTransaction;

            if ($originalTransaction) {
                // ✅ CALCULAR DEUDA DE LA TRANSACCIÓN ORIGINAL DESPUÉS DE ESTA DEVOLUCIÓN
                $originalDebtInfo = $originalTransaction->calculateDebtInfo();

                // ✅ INFO ESPECÍFICA DE LA DEVOLUCIÓN
                $returnedAmount = abs($transaction->total);
                $refundedAmount = abs($transaction->transactionPayments->sum('amount_paid'));

                $debtInfo = [
                    'original_total' => (float) $originalTransaction->total,
                    'total_returned' => $returnedAmount, // Solo esta devolución
                    'total_refunded' => $refundedAmount, // Solo reembolso de esta devolución
                    'net_total' => $returnedAmount, // Total de esta devolución
                    'amount_paid' => 0.0, // Las devoluciones no "pagan"
                    'current_debt' => (float) $originalDebtInfo['current_debt'], // ✅ DEUDA DE LA ORIGINAL
                    'payment_surplus' => (float) $originalDebtInfo['payment_surplus'], // ✅ SUPERÁVIT DE LA ORIGINAL
                ];

                // ✅ REFERENCIA A TRANSACCIÓN ORIGINAL
                $originalTransactionRef = [
                    'id' => $originalTransaction->id,
                    'code' => $originalTransaction->code,
                    'total' => (float) $originalTransaction->total,
                    'date' => $originalTransaction->date->format('Y-m-d'),
                    'agent_name' => $originalTransaction->agent?->name ?? 'N/A',
                    'transaction_type_name' => $originalTransaction->transactionType?->name ?? 'N/A'
                ];

                return new TransactionDTO(
                    id: $transaction->id,
                    code: $transaction->code,
                    agentId: $transaction->agent_id,
                    agentName: $transaction->agent?->name ?? 'N/A',
                    userId: $transaction->user_id,
                    userName: $transaction->user?->name ?? 'N/A',
                    zoneId: $transaction->zone_id,
                    zoneName: $transaction->zone?->name ?? 'N/A',
                    transactionTypeId: $transaction->transaction_type_id,
                    transactionTypeName: $transaction->transactionType?->name ?? 'N/A',
                    description: $transaction->description,
                    date: $transaction->date->format('Y-m-d'),
                    total: (float) $transaction->total, // Negativo para devoluciones
                    amountPaid: (float) $transaction->amount_paid, // Debería ser 0
                    deliveryStatus: $transaction->delivery_status,
                    paymentStatus: $transaction->payment_status,
                    tripId: $transaction->trip_id,
                    // ✅ USAR CÁLCULOS CORREGIDOS PARA DEVOLUCIONES
                    currentDebt: $debtInfo['current_debt'],
                    paymentSurplus: $debtInfo['payment_surplus'],
                    netTotal: $debtInfo['net_total'],
                    totalReturned: $debtInfo['total_returned'],
                    totalRefunded: $debtInfo['total_refunded'],
                    details: self::mapDetails($transaction),
                    payments: self::mapPayments($transaction),
                    returns: [], // Las devoluciones no tienen sub-devoluciones
                    relationTo: $transaction->relation_to,
                    originalTransaction: $originalTransactionRef,
                    canBeEdited: $transaction->canBeEdited(),
                    canBeDelivered: $transaction->canBeDelivered(),
                    canReceivePayment: $transaction->canReceivePayment(),
                    createdAt: $transaction->created_at->format('Y-m-d H:i:s'),
                    updatedAt: $transaction->updated_at->format('Y-m-d H:i:s'),
                );
            }
        }

        // ✅ PARA TRANSACCIONES ORIGINALES: CALCULAR DEUDA NORMALMENTE
        $debtInfo = $transaction->calculateDebtInfo();

        // ✅ MANEJAR DEVOLUCIONES ANIDADAS PARA TRANSACCIONES ORIGINALES
        $returns = $transaction->returns()
            ->whereNot('delivery_status', 'CANCELLED')
            ->whereNot('payment_status', 'CANCELLED')
            ->whereNull('deleted_at')
            ->with([
                'agent',
                'user',
                'transactionType',
                'transactionDetails.product.measureType',
                'transactionPayments.paymentMethod'
            ])
            ->get()
            ->map(function ($return) {
                return self::mapReturnToNestedArray($return);
            })
            ->toArray();

        return new TransactionDTO(
            id: $transaction->id,
            code: $transaction->code,
            agentId: $transaction->agent_id,
            agentName: $transaction->agent?->name ?? 'N/A',
            userId: $transaction->user_id,
            userName: $transaction->user?->name ?? 'N/A',
            zoneId: $transaction->zone_id,
            zoneName: $transaction->zone?->name ?? 'N/A',
            transactionTypeId: $transaction->transaction_type_id,
            transactionTypeName: $transaction->transactionType?->name ?? 'N/A',
            description: $transaction->description,
            date: $transaction->date->format('Y-m-d'),
            total: (float) $transaction->total,
            amountPaid: (float) $transaction->amount_paid,
            deliveryStatus: $transaction->delivery_status,
            paymentStatus: $transaction->payment_status,
            tripId: $transaction->trip_id,
            // ✅ USAR VALORES CALCULADOS CORRECTOS
            currentDebt: (float) $debtInfo['current_debt'],
            paymentSurplus: (float) $debtInfo['payment_surplus'],
            netTotal: (float) $debtInfo['net_total'],
            totalReturned: (float) $debtInfo['total_returned'],
            totalRefunded: (float) $debtInfo['total_refunded'],
            details: self::mapDetails($transaction),
            payments: self::mapPayments($transaction),
            returns: $returns,
            relationTo: $transaction->relation_to,
            originalTransaction: null, // Solo para devoluciones
            canBeEdited: $transaction->canBeEdited(),
            canBeDelivered: $transaction->canBeDelivered(),
            canReceivePayment: $transaction->canReceivePayment(),
            createdAt: $transaction->created_at->format('Y-m-d H:i:s'),
            updatedAt: $transaction->updated_at->format('Y-m-d H:i:s'),
        );
    }


    // ✅ MÉTODO CORREGIDO PARA MAPEAR DEVOLUCIONES CON REEMBOLSOS
    private static function mapReturnToNestedArray(Transaction $return): array
    {
        // ✅ CALCULAR REEMBOLSO CORRECTAMENTE
        $refundedAmount = abs($return->transactionPayments->sum('amount_paid'));

        return [
            'id' => $return->id,
            'code' => $return->code,
            'description' => $return->description,
            'date' => $return->date->format('Y-m-d'),
            'total' => (float) $return->total, // Negativo para devoluciones
            'amount_paid' => (float) $return->amount_paid, // Para transaction (debería ser 0)
            'delivery_status' => $return->delivery_status,
            'payment_status' => $return->payment_status,
            'transaction_type_name' => $return->transactionType?->name ?? 'N/A',

            // ✅ INFORMACIÓN ESPECÍFICA DE LA DEVOLUCIÓN CORREGIDA
            'returned_amount' => abs((float) $return->total), // Monto devuelto (positivo)
            'refunded_amount' => $refundedAmount, // ✅ DINERO REALMENTE REEMBOLSADO
            'net_return_amount' => abs((float) $return->total), // Para mostrar en UI

            // ✅ DETALLES Y PAGOS DE LA DEVOLUCIÓN
            'details' => self::mapDetails($return),
            'payments' => self::mapPayments($return),

            // ✅ METADATA
            'created_at' => $return->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $return->updated_at->format('Y-m-d H:i:s'),
            'user_name' => $return->user?->name ?? 'N/A',

            // ✅ INFORMACIÓN FORMATEADA CORREGIDA
            'formatted_returned_amount' => 'S/ ' . number_format(abs($return->total), 2),
            'formatted_refunded_amount' => 'S/ ' . number_format($refundedAmount, 2), // ✅ USAR CÁLCULO CORRECTO

            // ✅ INFORMACIÓN ADICIONAL PARA DEBUG
            'has_refund' => $refundedAmount > 0,
            'refund_payments_count' => $return->transactionPayments->count(),
        ];
    }

    public static function modelToDropdownDTO(Transaction $transaction): TransactionDropdownDTO
    {
        return new TransactionDropdownDTO(
            id: $transaction->id,
            code: $transaction->code,
            displayName: $transaction->code . ' - ' . ($transaction->agent?->name ?? 'Sin agente') . ' - S/ ' . number_format($transaction->total, 2),
            agentName: $transaction->agent?->name ?? 'Sin agente',
            total: (float) $transaction->total,
            date: $transaction->date->format('Y-m-d'),
            status: $transaction->delivery_status ?? 'PENDING'
        );
    }

    public static function collectionToDTOs($transactions): array
    {
        return $transactions->map(fn($transaction) => self::modelToDTO($transaction))->toArray();
    }

    public static function collectionToDropdownDTOs($transactions): array
    {
        return $transactions->map(fn($transaction) => self::modelToDropdownDTO($transaction))->toArray();
    }

    public static function paginatedToDTOs(LengthAwarePaginator $paginatedTransactions): array
    {
        return [
            'data' => $paginatedTransactions->getCollection()->map(fn($transaction) => self::modelToDTO($transaction)),
            'pagination' => [
                'current_page' => $paginatedTransactions->currentPage(),
                'last_page' => $paginatedTransactions->lastPage(),
                'per_page' => $paginatedTransactions->perPage(),
                'total' => $paginatedTransactions->total(),
                'from' => $paginatedTransactions->firstItem(),
                'to' => $paginatedTransactions->lastItem(),
            ]
        ];
    }

    private static function mapDetails(Transaction $transaction): array
    {
        return $transaction->transactionDetails->map(function ($detail) {
            return [
                'id' => $detail->id,
                'product_id' => $detail->product_id,
                'product_name' => $detail->product?->name,
                'product_code' => $detail->product?->code,
                'price' => (float) $detail->price,
                'quantity' => (float) $detail->quantity,
                'subtotal' => (float) $detail->subtotal,
                'measure_type' => $detail->product?->measureType?->symbol ?? 'Und'
            ];
        })->toArray();
    }

    private static function mapPayments(Transaction $transaction): array
    {
        return $transaction->transactionPayments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_method_id' => $payment->payment_method_id,
                'payment_method_name' => $payment->paymentMethod?->name,
                'amount_paid' => (float) $payment->amount_paid,
                'code' => $payment->code,
                'description' => $payment->description,
                'has_description' => !empty($payment->description),
                // ✅ INFORMACIÓN DE TIPO DE PAGO
                'payment_type' => $payment->getPaymentType(), // 'payment' o 'refund'
                'is_refund' => $payment->isRefund(),
                'is_payment' => $payment->isPayment(),
                'formatted_amount' => $payment->getFormattedAmount(),
                'created_at' => $payment->created_at->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }
}
