<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\DTOs\Transaction\TransactionDTO.php

namespace App\DTOs\Transaction;

class TransactionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly int $agentId,
        public readonly string $agentName,
        public readonly int $userId,
        public readonly string $userName,
        public readonly int $zoneId,
        public readonly string $zoneName,
        public readonly int $transactionTypeId,
        public readonly string $transactionTypeName,
        public readonly ?string $description,
        public readonly string $date,
        public readonly float $total,
        public readonly float $amountPaid,
        public readonly string $deliveryStatus,
        public readonly string $paymentStatus,
        public readonly ?int $tripId,
        // ✅ INFORMACIÓN DE DEUDA
        public readonly float $currentDebt,
        public readonly float $paymentSurplus,
        public readonly float $netTotal,
        public readonly float $totalReturned,
        public readonly float $totalRefunded,
        public readonly array $details,
        public readonly array $payments,
        // ✅ NUEVO: DEVOLUCIONES ANIDADAS
        public readonly array $returns,
        public readonly bool $canBeEdited,
        public readonly bool $canBeDelivered,
        public readonly bool $canReceivePayment,
        // ✅ NUEVO: RELACIÓN CON TRANSACCIÓN ORIGINAL
        public readonly ?int $relationTo,
        public readonly ?array $originalTransaction,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'agent_id' => $this->agentId,
            'agent_name' => $this->agentName,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'zone_id' => $this->zoneId,
            'zone_name' => $this->zoneName,
            'transaction_type_id' => $this->transactionTypeId,
            'transaction_type_name' => $this->transactionTypeName,
            'description' => $this->description,
            'date' => $this->date,
            'total' => $this->total,
            'amount_paid' => $this->amountPaid,
            'delivery_status' => $this->deliveryStatus,
            'payment_status' => $this->paymentStatus,
            'trip_id' => $this->tripId,
            
            // ✅ INFORMACIÓN DE DEUDA COMPLETA (SIN DEVOLUCIONES CANCELADAS)
            'debt_info' => [
                'current_debt' => $this->currentDebt,
                'payment_surplus' => $this->paymentSurplus,
                'net_total' => $this->netTotal,
                'total_returned' => $this->totalReturned, // Solo devoluciones activas
                'total_refunded' => $this->totalRefunded,
                'debt_status' => $this->currentDebt > 0.01 ? 'debtor' : ($this->paymentSurplus > 0.01 ? 'surplus' : 'paid'),
                'formatted_debt' => 'S/ ' . number_format($this->currentDebt, 2),
                'formatted_surplus' => 'S/ ' . number_format($this->paymentSurplus, 2),
                'formatted_net_total' => 'S/ ' . number_format($this->netTotal, 2),
                'formatted_total_returned' => 'S/ ' . number_format($this->totalReturned, 2),
                'formatted_total_refunded' => 'S/ ' . number_format($this->totalRefunded, 2),
                'payment_percentage' => $this->netTotal > 0 ? round(($this->amountPaid / $this->netTotal) * 100, 2) : 0
            ],
            
            'details' => $this->details,
            'payments' => $this->payments,
            'returns' => $this->returns, // Solo devoluciones activas
            'returns_count' => count($this->returns), // Solo activas
            'has_returns' => count($this->returns) > 0, // Solo activas
            
            'relation_to' => $this->relationTo,
            'original_transaction' => $this->originalTransaction,
            'is_return' => !is_null($this->relationTo),
            'is_original' => is_null($this->relationTo),
            
            'can_be_edited' => $this->canBeEdited,
            'can_be_delivered' => $this->canBeDelivered,
            'can_receive_payment' => $this->canReceivePayment,
            
            // ✅ INFORMACIÓN DE CANCELACIÓN ACTUALIZADA
            'cancellation_info' => [
                'can_cancel' => $this->deliveryStatus !== 'CANCELLED' && $this->paymentStatus !== 'CANCELLED',
                'is_cancelled' => $this->deliveryStatus === 'CANCELLED' || $this->paymentStatus === 'CANCELLED',
                'delivery_cancelled' => $this->deliveryStatus === 'CANCELLED',
                'payment_cancelled' => $this->paymentStatus === 'CANCELLED',
                'action_type' => !is_null($this->relationTo) ? 'anular' : 'cancelar',
                'can_cancel_reason' => $this->getCancellationReasonForDTO(),
                'rules' => [
                    'original' => 'Solo se pueden cancelar si están PENDING (no entregadas)',
                    'returns' => 'Las devoluciones se pueden anular en cualquier estado',
                    'effect_on_cancel' => !is_null($this->relationTo) ? 
                        'Anular revertirá stock y reembolsos' : 
                        'Cancelar liberará stock reservado'
                ]
            ],
            
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // ✅ MÉTODO AGREGADO: OBTENER RAZÓN DE CANCELACIÓN PARA DTO
    private function getCancellationReasonForDTO(): ?string
    {
        if ($this->deliveryStatus === 'CANCELLED' || $this->paymentStatus === 'CANCELLED') {
            return 'Ya está cancelada';
        }
        
        if (is_null($this->relationTo)) {
            // Transacción original
            if ($this->deliveryStatus !== 'PENDING') {
                return 'Solo se pueden cancelar transacciones PENDING (no entregadas)';
            }
        }
        
        // Las devoluciones siempre se pueden anular
        return null;
    }
}