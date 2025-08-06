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
        // ✅ OBTENER INFORMACIÓN DE DEUDA CORREGIDA
        $debtInfo = $transaction->calculateDebtInfo();
        
        // ✅ MANEJAR DEVOLUCIONES ANIDADAS VS REFERENCIA A ORIGINAL
        $returns = [];
        $originalTransaction = null;
        
        if ($transaction->isReturn()) {
            // ✅ ES UNA DEVOLUCIÓN: incluir referencia a la transacción original
            if ($transaction->originalTransaction) {
                $originalTransaction = [
                    'id' => $transaction->originalTransaction->id,
                    'code' => $transaction->originalTransaction->code,
                    'total' => (float) $transaction->originalTransaction->total,
                    'date' => $transaction->originalTransaction->date->format('Y-m-d'),
                    'agent_name' => $transaction->originalTransaction->agent?->name ?? 'N/A',
                    'transaction_type_name' => $transaction->originalTransaction->transactionType?->name ?? 'N/A'
                ];
            }
        } else {
            // ✅ ES TRANSACCIÓN ORIGINAL: incluir devoluciones anidadas
            $returns = $transaction->returns()
                ->with([
                    'agent', 'user', 'transactionType',
                    'transactionDetails.product.measureType', 
                    'transactionPayments.paymentMethod'
                ])
                ->get()
                ->map(function ($return) {
                    return self::mapReturnToNestedArray($return);
                })
                ->toArray();
        }
        
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
            // ✅ USAR VALORES CALCULADOS CORREGIDOS
            currentDebt: (float) $debtInfo['current_debt'],
            paymentSurplus: (float) $debtInfo['payment_surplus'], 
            netTotal: (float) $debtInfo['net_total'],
            totalReturned: (float) $debtInfo['total_returned'],
            totalRefunded: (float) ($debtInfo['total_refunded'] ?? 0),
            details: self::mapDetails($transaction),
            payments: self::mapPayments($transaction),
            // ✅ DEVOLUCIONES ANIDADAS O REFERENCIA A ORIGINAL
            returns: $returns,
            relationTo: $transaction->relation_to,
            originalTransaction: $originalTransaction,
            // ✅ USAR MÉTODOS QUE AHORA EXISTEN
            canBeEdited: $transaction->canBeEdited(),
            canBeDelivered: $transaction->canBeDelivered(),
            canReceivePayment: $transaction->canReceivePayment(),
            createdAt: $transaction->created_at->format('Y-m-d H:i:s'),
            updatedAt: $transaction->updated_at->format('Y-m-d H:i:s'),
        );
    }

    // ✅ NUEVO MÉTODO: MAPEAR DEVOLUCIÓN A ARRAY ANIDADO (SIN DTO COMPLETO)
    private static function mapReturnToNestedArray(Transaction $return): array
    {
        $debtInfo = $return->calculateDebtInfo();
        
        return [
            'id' => $return->id,
            'code' => $return->code,
            'description' => $return->description,
            'date' => $return->date->format('Y-m-d'),
            'total' => (float) $return->total, // Negativo para devoluciones
            'amount_paid' => (float) $return->amount_paid, // 0 para devoluciones en transaction
            'delivery_status' => $return->delivery_status,
            'payment_status' => $return->payment_status,
            'transaction_type_name' => $return->transactionType?->name ?? 'N/A',
            
            // ✅ INFORMACIÓN ESPECÍFICA DE LA DEVOLUCIÓN
            'returned_amount' => abs((float) $return->total), // Monto devuelto (positivo)
            'refunded_amount' => (float) $return->transactionPayments->sum('amount_paid'), // Dinero reembolsado
            'net_return_amount' => abs((float) $return->total), // Para mostrar en UI
            
            // ✅ DETALLES Y PAGOS DE LA DEVOLUCIÓN
            'details' => self::mapDetails($return),
            'payments' => self::mapPayments($return),
            
            // ✅ METADATA
            'created_at' => $return->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $return->updated_at->format('Y-m-d H:i:s'),
            'user_name' => $return->user?->name ?? 'N/A',
            
            // ✅ INFORMACIÓN FORMATEADA
            'formatted_returned_amount' => 'S/ ' . number_format(abs($return->total), 2),
            'formatted_refunded_amount' => 'S/ ' . number_format($return->transactionPayments->sum('amount_paid'), 2),
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