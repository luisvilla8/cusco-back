<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Egress;
use App\Models\TransactionPayment;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agent_id',
        'user_id',
        'description',
        'code',
        'zone_id',
        'transaction_type_id',
        'relation_to',
        'amount_paid',
        'date',
        'total',
        'trip_id',
        'delivery_status',
        'payment_status'
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'total' => 'decimal:2',
        'date' => 'date',
        'delivery_status' => 'string',
        'payment_status' => 'string',
    ];

    protected $attributes = [
        'delivery_status' => self::DELIVERY_STATUS_PENDING,
        'payment_status' => self::PAYMENT_STATUS_PENDING,
    ];

    // ✅ STATUS CONSTANTS
    const DELIVERY_STATUS_PENDING = 'PENDING';
    const DELIVERY_STATUS_DELIVERED = 'DELIVERED';
    const DELIVERY_STATUS_RETURNED = 'RETURNED';
    const DELIVERY_STATUS_CANCELLED = 'CANCELLED';

    const PAYMENT_STATUS_PENDING = 'PENDING';
    const PAYMENT_STATUS_PARTIAL = 'PARTIAL';
    const PAYMENT_STATUS_PAID = 'PAID';
    const PAYMENT_STATUS_CANCELLED = 'CANCELLED';

    // ✅ TRANSACTION TYPE CONSTANTS
    const TYPE_SALE = 'SALE';
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_RETURN_SALE = 'RETURN_SALE';
    const TYPE_RETURN_PURCHASE = 'RETURN_PURCHASE';

    // ✅ BOOT METHOD CORREGIDO
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->code)) {
                $transaction->code = $transaction->generateCode();
            }

            if (is_null($transaction->payment_status) || $transaction->payment_status === self::PAYMENT_STATUS_PENDING) {
                $transaction->updatePaymentStatusAutomatically();
            }
        });

        static::created(function ($transaction) {
            // ✅ APLICAR REGLAS DE STOCK DESPUÉS DE CREAR TRANSACCIÓN
            $transaction->applyStockRulesOnCreate();
        });

        static::updating(function ($transaction) {
            if ($transaction->isDirty(['amount_paid', 'total'])) {
                $transaction->updatePaymentStatusAutomatically();
            }
        });

        static::deleting(function ($transaction) {
            if (!$transaction->isForceDeleting()) {
                // ✅ APLICAR REGLA 5 AL SOFT DELETE
                $transaction->applyStockRulesOnCancel();
            }
        });
    }

    // ✅ MÉTODO NUEVO: APLICAR REGLAS AL CREAR TRANSACCIÓN
    private function applyStockRulesOnCreate(): void
    {
        $transactionType = $this->transactionType?->code;

        if ($transactionType === self::TYPE_SALE && $this->isDeliveryPending()) {
            // ✅ REGLA 1: VENTA PENDING - Solo reservar stock
            $this->applySaleCreationRule();
        } elseif ($transactionType === self::TYPE_SALE && $this->isDelivered()) {
            // ✅ VENTA CREADA COMO ENTREGADA - Aplicar regla 3 directamente
            $this->applySaleDeliveryRule();
        } elseif ($transactionType === self::TYPE_PURCHASE && $this->isDelivered()) {
            // ✅ REGLA 6: COMPRA ENTREGADA - Aumentar stock
            $this->applyPurchaseRule();
        } elseif ($transactionType === self::TYPE_RETURN_SALE) {
            // ✅ REGLA 4: DEVOLUCIÓN DE VENTA
            $this->applySaleReturnRule();
        } elseif ($transactionType === self::TYPE_RETURN_PURCHASE) {
            // ✅ DEVOLUCIÓN DE COMPRA - Reducir stock
            $this->applyPurchaseReturnRule();
        }
    }

    // ✅ REGLA 1: CREAR VENTA PENDING
    private function applySaleCreationRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 1: reserved_stock += cantidad (stock no cambia)
            $product->reserveStock($detail->quantity);
        }
    }

    // ✅ REGLA 3: APLICAR ENTREGA DE VENTA
    private function applySaleDeliveryRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 3: reserved_stock -= cantidad, stock -= cantidad
            $product->confirmSaleDelivery($detail->quantity);
        }
    }

    // ✅ REGLA 6: APLICAR COMPRA
    private function applyPurchaseRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 6: stock += cantidad (reserved_stock no se toca)
            $product->processPurchase($detail->quantity);
        }
    }

    // ✅ REGLA 4 CORREGIDA: APLICAR DEVOLUCIÓN DE VENTA
    private function applySaleReturnRule(): void
    {
        if (!$this->relation_to) return;

        $originalTransaction = Transaction::find($this->relation_to);
        if (!$originalTransaction) return;

        $wasDelivered = $originalTransaction->isDelivered();

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 4: Según si fue entregado o no
            $product->processSaleReturn(abs($detail->quantity), $wasDelivered);
        }
    }

    // ✅ APLICAR DEVOLUCIÓN DE COMPRA
    private function applyPurchaseReturnRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // Devolución de compra = reducir stock
            $product->cancelPurchase(abs($detail->quantity));
        }
    }

    // ✅ REGLA 5 CORREGIDA: CANCELAR VENTA
    private function applySaleCancellationRule(): void
    {
        $wasDelivered = $this->isDelivered();

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            if ($wasDelivered) {
                // ✅ Ya entregado: Solo restaurar stock (no hay reserved_stock)
                $product->increment('stock', $detail->quantity);

                \Log::info('Delivered sale cancelled - stock restored', [
                    'transaction_id' => $this->id,
                    'product_id' => $product->id,
                    'quantity_restored' => $detail->quantity,
                    'stock_after' => $product->fresh()->stock
                ]);
            } else {
                // ✅ REGLA 5: PENDING - reserved_stock -= cantidad, stock += cantidad
                $product->cancelSale($detail->quantity);
            }
        }
    }

    // ✅ REGLA 7: CANCELAR COMPRA
    private function applyPurchaseCancellationRule(): void
    {
        if (!$this->isDelivered()) {
            \Log::info('Pending purchase cancelled - no stock to revert', [
                'transaction_id' => $this->id
            ]);
            return; // No hay stock que revertir si no se entregó
        }

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 7: stock -= cantidad
            $product->cancelPurchase($detail->quantity);
        }
    }

    // ✅ REGLA 2 CORREGIDA: ACTUALIZAR RESERVAS AL EDITAR TRANSACCIÓN
    public function updateSaleReservations(array $oldDetails, array $newDetails): void
    {
        if (!$this->isSale() || !$this->isDeliveryPending()) {
            return;
        }

        // ✅ REGLA 2: Actualizar reservas de cada producto
        $oldByProduct = collect($oldDetails)->groupBy('product_id');
        $newByProduct = collect($newDetails)->groupBy('product_id');

        // Obtener todos los productos afectados
        $allProductIds = $oldByProduct->keys()->merge($newByProduct->keys())->unique();

        foreach ($allProductIds as $productId) {
            $product = Product::find($productId);
            if (!$product) continue;

            $oldQuantity = $oldByProduct->get($productId, collect())->sum('quantity');
            $newQuantity = $newByProduct->get($productId, collect())->sum('quantity');

            if ($oldQuantity != $newQuantity) {
                // ✅ REGLA 2: Actualizar reserva correctamente
                $product->updateReservation($oldQuantity, $newQuantity);
            }
        }
    }

    // ✅ REVERTIR EFECTOS DE DEVOLUCIÓN CANCELADA
    private function revertReturnEffects(): void
    {
        $returnType = $this->transactionType?->code;

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $quantity = abs($detail->quantity);

            switch ($returnType) {
                case self::TYPE_RETURN_SALE:
                    // ✅ CANCELAR DEVOLUCIÓN DE VENTA: Quitar el stock que se había restaurado
                    if ($product->stock >= $quantity) {
                        $product->decrement('stock', $quantity);

                        \Log::info('Sale return cancelled - stock reduced', [
                            'transaction_id' => $this->id,
                            'product_id' => $product->id,
                            'quantity_reduced' => $quantity,
                            'stock_after' => $product->fresh()->stock
                        ]);
                    } else {
                        \Log::warning('Insufficient stock to revert sale return cancellation', [
                            'transaction_id' => $this->id,
                            'product_id' => $product->id,
                            'required_quantity' => $quantity,
                            'current_stock' => $product->stock
                        ]);
                    }
                    break;

                case self::TYPE_RETURN_PURCHASE:
                    // ✅ CANCELAR DEVOLUCIÓN DE COMPRA: Restaurar el stock que se había quitado
                    $product->increment('stock', $quantity);

                    \Log::info('Purchase return cancelled - stock restored', [
                        'transaction_id' => $this->id,
                        'product_id' => $product->id,
                        'quantity_restored' => $quantity,
                        'stock_after' => $product->fresh()->stock
                    ]);
                    break;
            }
        }

        $this->revertRefunds();
    }

    // ✅ MÉTODO CORREGIDO: LIBERAR STOCK RESERVADO
    private function releaseReservedStock(): void
    {
        if (!$this->isSale()) {
            return;
        }

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $product->releaseReservedStock($detail->quantity);
        }
    }

    // ✅ MÉTODO UNIFICADO: CANCELAR TRANSACCIONES
    public function cancelTransaction(): void
    {
        DB::transaction(function () {
            $this->validateCancellation();

            if ($this->isOriginalTransaction()) {
                $this->cancelOriginalTransactionWithCascade();
            } elseif ($this->isReturn()) {
                $this->cancelReturnTransaction();
            }

            // ✅ REGLA 5: MARCAR COMO CANCELADO Y SOFT DELETE
            $this->update([
                'delivery_status' => self::DELIVERY_STATUS_CANCELLED,
                'payment_status' => self::PAYMENT_STATUS_CANCELLED
            ]);

            // ✅ REGLA 5: SOFT DELETE (deleted_at = fecha actual)
            $this->delete();
        });
    }


    // ✅ NUEVO MÉTODO: ANULAR TODAS LAS DEVOLUCIONES RELACIONADAS
    private function cancelAllRelatedReturns(): void
    {
        $activeReturns = $this->returns()
            ->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])
            ->whereNull('deleted_at')
            ->get();

        foreach ($activeReturns as $return) {
            // Revertir efectos si la devolución ya estaba procesada
            if ($return->delivery_status === self::DELIVERY_STATUS_RETURNED) {
                $return->revertReturnEffects();
            }

            // Marcar como cancelada y soft delete
            $return->update([
                'delivery_status' => self::DELIVERY_STATUS_CANCELLED,
                'payment_status' => self::PAYMENT_STATUS_CANCELLED
            ]);

            // Soft delete de detalles y pagos de la devolución
            $return->softDeleteTransactionDetails();
            $return->softDeleteTransactionPayments();

            // Soft delete de la devolución
            $return->delete();
        }
    }

    // ✅ NUEVO MÉTODO: REVERTIR STOCK DE TRANSACCIÓN ENTREGADA
    private function revertDeliveredTransactionStock(): void
    {
        $transactionType = $this->transactionType?->code;

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $quantity = $detail->quantity;

            switch ($transactionType) {
                case self::TYPE_SALE:
                    // Restaurar stock que se había reducido
                    $product->updateStock($quantity, 'ADD');
                    break;

                case self::TYPE_PURCHASE:
                    // Reducir stock que se había añadido
                    if ($product->stock >= $quantity) {
                        $product->updateStock($quantity, 'SUBTRACT');
                    }
                    break;
            }
        }
    }

    // ✅ NUEVO MÉTODO: SOFT DELETE DE DETALLES
    private function softDeleteTransactionDetails(): void
    {
        $this->transactionDetails()->delete(); // Soft delete
    }

    // ✅ NUEVO MÉTODO: SOFT DELETE DE PAGOS
    private function softDeleteTransactionPayments(): void
    {
        $this->transactionPayments()->delete(); // Soft delete
    }

    // ✅ ACTUALIZAR MÉTODO DE DEVOLUCIONES
    private function cancelReturnTransaction(): void
    {
        if ($this->delivery_status === self::DELIVERY_STATUS_RETURNED) {
            $this->revertReturnEffects();
        }

        // Soft delete de detalles y pagos
        $this->softDeleteTransactionDetails();
        $this->softDeleteTransactionPayments();

        $this->updateOriginalTransactionAfterReturnCancellation();
    }

    private function validateCancellation(): void
    {
        if (!$this->canBeCancelled()) {
            $reason = $this->getCancellationBlockReason();
            throw new \InvalidArgumentException("No se puede cancelar: {$reason}");
        }
    }

    private function cancelOriginalTransaction(): void
    {
        if ($this->isSale()) {
            $this->releaseReservedStock();
        }

        if ($this->isPurchase()) {
            $this->deleteRelatedEgress();
        }
    }




    private function revertRefunds(): void
    {
        $refunds = $this->transactionPayments()->get();

        foreach ($refunds as $refund) {
            $refund->delete();
        }

        $this->amount_paid = 0;
    }

    private function updateOriginalTransactionAfterReturnCancellation(): void
    {
        if (!$this->relation_to) return;

        $originalTransaction = Transaction::find($this->relation_to);
        if ($originalTransaction) {
            $originalTransaction->updatePaymentStatusAutomatically();
            $originalTransaction->save();
        }
    }


    public function hasActiveReturns(): bool
    {
        return $this->returns()
            ->whereNotIn('delivery_status', [
                self::DELIVERY_STATUS_CANCELLED
            ])
            ->whereNull('deleted_at') // ✅ EXCLUIR SOFT DELETED
            ->exists();
    }

    public function getActiveReturns(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->returns()
            ->whereNotIn('delivery_status', [
                self::DELIVERY_STATUS_CANCELLED
            ])
            ->whereNull('deleted_at') // ✅ EXCLUIR SOFT DELETED
            ->with(['transactionDetails.product', 'transactionPayments.paymentMethod'])
            ->get();
    }

    // ✅ NUEVO MÉTODO: OBTENER DEVOLUCIONES ANULADAS (SOFT DELETED)
    public function getCancelledReturns(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->hasMany(Transaction::class, 'relation_to')
            ->withTrashed() // Incluir soft deleted
            ->where('delivery_status', self::DELIVERY_STATUS_CANCELLED)
            ->whereNotNull('deleted_at') // Solo las soft deleted
            ->with(['transactionDetails.product', 'transactionPayments.paymentMethod'])
            ->get();
    }

    // ✅ PAGOS
    public function canReceivePayment(): bool
    {
        if ($this->isPaymentCancelled() || $this->isDeliveryCancelled()) {
            return false;
        }

        if ($this->isFullyPaid()) {
            return false;
        }

        return $this->isPaymentPending() || $this->isPartiallyPaid();
    }

    public function getRemainingAmount(): float
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function getFormattedRemainingAmount(): string
    {
        return "S/ " . number_format($this->getRemainingAmount(), 2);
    }

    public function canReceiveAmount(float $amount): bool
    {
        if (!$this->canReceivePayment()) {
            return false;
        }

        return $amount <= $this->getRemainingAmount();
    }

    public function getPaymentProgress(): float
    {
        if ($this->total <= 0) return 0;
        return round(($this->amount_paid / $this->total) * 100, 2);
    }

    public function isValidTransaction(): bool
    {
        return $this->total > 0 &&
            $this->amount_paid >= 0 &&
            $this->amount_paid <= $this->total &&
            !$this->isDeliveryCancelled() &&
            !$this->isPaymentCancelled();
    }

    private function revertStockMovement(): void
    {
        $transactionType = $this->transactionType?->code;

        foreach ($this->transactionDetails as $detail) {
            $product = Product::find($detail->product_id);
            if (!$product) continue;

            if ($transactionType === self::TYPE_SALE) {
                $product->updateStock($detail->quantity, 'ADD');
            } elseif ($transactionType === self::TYPE_PURCHASE) {
                if ($product->hasStock($detail->quantity)) {
                    $product->updateStock($detail->quantity, 'SUBTRACT');
                }
            }
        }
    }

    // ✅ PRECIOS ORIGINALES
    public function getOriginalPriceForProduct(int $productId): ?float
    {
        $detail = $this->transactionDetails()
            ->where('product_id', $productId)
            ->first();

        return $detail ? (float) $detail->price : null;
    }

    public function getOriginalPrices(): array
    {
        return $this->transactionDetails()
            ->get()
            ->mapWithKeys(function ($detail) {
                return [$detail->product_id => (float) $detail->price];
            })
            ->toArray();
    }

    public function validateReturnPrices(array $returnDetails): array
    {
        $errors = [];
        $originalPrices = $this->getOriginalPrices();

        foreach ($returnDetails as $index => $detail) {
            $productId = $detail['product_id'] ?? null;
            $sentPrice = (float) ($detail['price'] ?? 0);

            if (!$productId) continue;

            if (!isset($originalPrices[$productId])) {
                $errors["details.{$index}.product_id"] = "El producto no se encuentra en la transacción original.";
                continue;
            }

            $originalPrice = $originalPrices[$productId];
            if (abs($sentPrice - $originalPrice) > 0.01) {
                $errors["details.{$index}.price"] = "El precio debe ser el precio original: S/ {$originalPrice}. Precio enviado: S/ {$sentPrice}";
            }
        }

        return $errors;
    }

    public function hasPaymentSurplus(): bool
    {
        return $this->getPaymentSurplus() > 0;
    }

    public function calculateReturnAmounts(float $returnAmount): array
    {
        $currentDebt = $this->getCurrentDebt();
        $paymentSurplus = $this->getPaymentSurplus();

        $refundAmount = 0;
        $debtCompensation = 0;

        if ($currentDebt > 0) {
            $debtCompensation = min($returnAmount, $currentDebt);
            $refundAmount = max(0, $returnAmount - $currentDebt);
        } else {
            $refundAmount = $returnAmount;
        }

        return [
            'refund_amount' => (float) $refundAmount,
            'debt_compensation' => (float) $debtCompensation,
            'current_debt' => (float) $currentDebt,
            'payment_surplus' => (float) $paymentSurplus,
            'return_amount' => (float) $returnAmount
        ];
    }

    // ✅ STATUS CHECKS
    public function isDeliveryPending(): bool
    {
        return $this->delivery_status === self::DELIVERY_STATUS_PENDING;
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === self::DELIVERY_STATUS_DELIVERED;
    }

    public function isDeliveryReturned(): bool
    {
        return $this->delivery_status === self::DELIVERY_STATUS_RETURNED;
    }

    public function isDeliveryCancelled(): bool
    {
        return $this->delivery_status === self::DELIVERY_STATUS_CANCELLED;
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PENDING;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PARTIAL;
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    public function isPaymentCancelled(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_CANCELLED;
    }

    // ✅ CAPABILITIES
    public function canBeEdited(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

    public function canBeDelivered(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

    public function isCompleted(): bool
    {
        return $this->isDelivered() && $this->isFullyPaid();
    }

    public function isPendingDelivery(): bool
    {
        return $this->isDeliveryPending();
    }

    public function isPendingPayment(): bool
    {
        return $this->isPaymentPending() || $this->isPartiallyPaid();
    }

    public function isActive(): bool
    {
        return !$this->isDeliveryCancelled() && !$this->isPaymentCancelled() && is_null($this->deleted_at);
    }

    // ✅ VALIDACIONES
    public function hasValidStatus(): bool
    {
        $validDeliveryStatuses = [
            self::DELIVERY_STATUS_PENDING,
            self::DELIVERY_STATUS_DELIVERED,
            self::DELIVERY_STATUS_RETURNED,
            self::DELIVERY_STATUS_CANCELLED
        ];

        $validPaymentStatuses = [
            self::PAYMENT_STATUS_PENDING,
            self::PAYMENT_STATUS_PARTIAL,
            self::PAYMENT_STATUS_PAID,
            self::PAYMENT_STATUS_CANCELLED
        ];

        return in_array($this->delivery_status, $validDeliveryStatuses) &&
            in_array($this->payment_status, $validPaymentStatuses);
    }

    public function canChangeStatusTo(string $newDeliveryStatus, string $newPaymentStatus = null): bool
    {
        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }

        switch ($newDeliveryStatus) {
            case self::DELIVERY_STATUS_DELIVERED:
                return $this->isDeliveryPending();

            case self::DELIVERY_STATUS_RETURNED:
                return $this->isDelivered() || $this->isDeliveryPending();

            case self::DELIVERY_STATUS_CANCELLED:
                return !$this->isDelivered();

            default:
                return true;
        }
    }

    // ✅ SCOPES ADICIONALES
    public function scopePendingDelivery(Builder $query): Builder
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_PENDING);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_DELIVERED);
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_RETURNED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_CANCELLED);
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->whereIn('payment_status', [
            self::PAYMENT_STATUS_PENDING,
            self::PAYMENT_STATUS_PARTIAL
        ]);
    }

    public function scopeFullyPaid(Builder $query): Builder
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('delivery_status', self::DELIVERY_STATUS_DELIVERED)
            ->where('payment_status', self::PAYMENT_STATUS_PAID);
    }

    // ✅ AGREGAR ESTE MÉTODO DESPUÉS DE LOS BOOT EVENTS
    // ✅ GENERAR CÓDIGO
    public function generateCode(): string
    {
        $typeCode = $this->transactionType?->code ?? 'TXN';

        $prefix = match ($typeCode) {
            self::TYPE_SALE => 'SALE',
            self::TYPE_PURCHASE => 'PURCHASE',
            self::TYPE_RETURN_SALE => 'RETURN-SALE',
            self::TYPE_RETURN_PURCHASE => 'RETURN-PURCHASE',
            default => 'TXN'
        };

        $date = now()->format('dmy');
        $sequential = static::whereDate('created_at', now())
            ->whereHas('transactionType', function ($q) use ($typeCode) {
                $q->where('code', $typeCode);
            })
            ->count() + 1;

        return "{$prefix}-{$date}-" . str_pad($sequential, 3, '0', STR_PAD_LEFT);
    }

    // ✅ PAYMENT STATUS
    public function updatePaymentStatusAutomatically(): void
    {
        if ($this->isPaymentCancelled()) {
            return;
        }

        $newStatus = $this->calculatePaymentStatus();

        if ($this->payment_status !== $newStatus) {
            $this->payment_status = $newStatus;

            if (!$this->exists) {
                // Durante creación
            } else {
                static::withoutEvents(function () use ($newStatus) {
                    $this->update(['payment_status' => $newStatus]);
                });
            }
        }
    }

    public function calculatePaymentStatus(): string
    {
        if ($this->isReturn()) {
            return self::PAYMENT_STATUS_PAID;
        }

        if ($this->isDeliveryCancelled()) {
            return self::PAYMENT_STATUS_CANCELLED;
        }

        $total = (float) $this->total;
        $amountPaid = (float) $this->amount_paid;

        if ($amountPaid <= 0.01) {
            return self::PAYMENT_STATUS_PENDING;
        }

        if ($amountPaid >= ($total - 0.01)) {
            return self::PAYMENT_STATUS_PAID;
        }

        return self::PAYMENT_STATUS_PARTIAL;
    }

    // ✅ MÉTODO HELPER PARA getCurrentDebt() Y getPaymentSurplus()
    public function getCurrentDebt(): float
    {
        return $this->calculateDebtInfo()['current_debt'];
    }

    public function getPaymentSurplus(): float
    {
        return $this->calculateDebtInfo()['payment_surplus'];
    }

    // ✅ MARK AS RETURNED
    public function markAsReturned(): void
    {
        DB::transaction(function () {
            $this->delivery_status = self::DELIVERY_STATUS_RETURNED;

            if ($this->isReturn()) {
                $this->payment_status = self::PAYMENT_STATUS_PAID;
            }

            $this->save();

            if ($this->isReturn() && $this->relation_to) {
                $originalTransaction = Transaction::find($this->relation_to);
                if ($originalTransaction) {
                    $originalTransaction->updatePaymentStatusAutomatically();
                    $originalTransaction->save();
                }
            }
        });
    }

    // ✅ MÉTODO HELPER PARA ELIMINAR EGRESO RELACIONADO
    private function deleteRelatedEgress(): void
    {
        $egress = Egress::where('transaction_id', $this->id)->first();

        if ($egress) {
            $egress->delete();
        }
    }

    // ✅ AGREGAR ESTAS RELACIONES DESPUÉS DE protected $attributes
    // ✅ RELACIONES FALTANTES
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function transactionPayments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    // ✅ RELACIONES PARA DEVOLUCIONES
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'relation_to');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(Transaction::class, 'relation_to');
    }

    // ✅ MÉTODOS HELPER PARA TIPOS DE TRANSACCIÓN
    public function isSale(): bool
    {
        return $this->transactionType?->code === self::TYPE_SALE;
    }

    public function isPurchase(): bool
    {
        return $this->transactionType?->code === self::TYPE_PURCHASE;
    }

    public function isReturn(): bool
    {
        return in_array($this->transactionType?->code, [self::TYPE_RETURN_SALE, self::TYPE_RETURN_PURCHASE]);
    }

    public function isOriginalTransaction(): bool
    {
        return is_null($this->relation_to) && in_array($this->transactionType?->code, [self::TYPE_SALE, self::TYPE_PURCHASE]);
    }

    public function canBeCancelled(): bool
    {
        // No se puede cancelar si ya está cancelada
        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }

        // No se puede cancelar si está soft deleted
        if (!is_null($this->deleted_at)) {
            return false;
        }

        return true;
    }

    public function getCancellationBlockReason(): string
    {
        if ($this->isDeliveryCancelled()) {
            return "La transacción ya está cancelada";
        }

        if ($this->isPaymentCancelled()) {
            return "Los pagos ya están cancelados";
        }

        if (!is_null($this->deleted_at)) {
            return "La transacción ya fue eliminada";
        }

        return "Estado de la transacción no válido";
    }

    public function canBeReturned(): bool
    {
        // Solo transacciones originales pueden tener devoluciones
        if (!$this->isOriginalTransaction()) {
            return false;
        }

        // No se puede devolver si está cancelada
        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }

        // Para ventas: debe estar entregada para poder devolver
        if ($this->isSale()) {
            return $this->isDelivered();
        }

        // Para compras: puede estar entregada o pending
        if ($this->isPurchase()) {
            return $this->isDelivered() || $this->isDeliveryPending();
        }

        return false;
    }

    // ✅ MÉTODO PARA CALCULAR INFORMACIÓN DE DEUDA
    public function calculateDebtInfo(): array
    {
        if ($this->isReturn()) {
            // Para devoluciones, no calcular deuda propia
            return [
                'current_debt' => 0.0,
                'payment_surplus' => 0.0,
                'net_total' => abs((float) $this->total),
                'total_returned' => 0.0,
                'total_refunded' => abs($this->transactionPayments->sum('amount_paid'))
            ];
        }

        // Para transacciones originales
        $originalTotal = (float) $this->total;
        $originalPaid = (float) $this->amount_paid;

        // Calcular total devuelto (solo devoluciones activas)
        $totalReturned = $this->returns()
            ->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])
            ->whereNull('deleted_at')
            ->sum('total');
        $totalReturned = abs($totalReturned); // Convertir a positivo

        // Calcular total reembolsado
        $totalRefunded = 0;
        foreach ($this->returns()->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])->whereNull('deleted_at')->get() as $return) {
            $totalRefunded += $return->transactionPayments->sum('amount_paid');
        }

        // Net total después de devoluciones
        $netTotal = $originalTotal - $totalReturned;

        // Calcular deuda actual
        $currentDebt = max(0, $netTotal - $originalPaid);

        // Calcular superávit
        $paymentSurplus = max(0, $originalPaid - $netTotal);

        return [
            'current_debt' => (float) $currentDebt,
            'payment_surplus' => (float) $paymentSurplus,
            'net_total' => (float) $netTotal,
            'total_returned' => (float) $totalReturned,
            'total_refunded' => (float) $totalRefunded
        ];
    }

    // ✅ MÉTODOS PARA OBTENER TOTALES
    public function getTotalReturnedAmount(): float
    {
        return abs($this->returns()
            ->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])
            ->whereNull('deleted_at')
            ->sum('total'));
    }

    public function getRemainingReturnableAmount(): float
    {
        if (!$this->canBeReturned()) {
            return 0;
        }

        $totalReturned = $this->getTotalReturnedAmount();
        return max(0, $this->total - $totalReturned);
    }

    // ✅ MÉTODO PARA CANCELAR TRANSACCIÓN ORIGINAL CON CASCADA
    private function cancelOriginalTransactionWithCascade(): void
    {
        // ✅ 1. ANULAR TODAS LAS DEVOLUCIONES RELACIONADAS PRIMERO
        $this->cancelAllRelatedReturns();

        // ✅ 2. APLICAR REGLAS DE CANCELACIÓN SEGÚN TIPO Y ESTADO
        if ($this->isSale()) {
            $this->applySaleCancellationRule();
        } elseif ($this->isPurchase()) {
            $this->applyPurchaseCancellationRule();
        }

        // ✅ 3. ELIMINAR EGRESO SI ES COMPRA
        if ($this->isPurchase()) {
            $this->deleteRelatedEgress();
        }

        // ✅ 4. SOFT DELETE DE DETALLES Y PAGOS
        $this->softDeleteTransactionDetails();
        $this->softDeleteTransactionPayments();
    }

    // ✅ SCOPES FALTANTES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->whereHas('transactionType', function ($q) use ($type) {
            $q->where('code', $type);
        });
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->byType(self::TYPE_SALE);
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query->byType(self::TYPE_PURCHASE);
    }

    public function scopeReturns(Builder $query): Builder
    {
        return $query->whereHas('transactionType', function ($q) {
            $q->whereIn('code', [self::TYPE_RETURN_SALE, self::TYPE_RETURN_PURCHASE]);
        });
    }

    public function scopeReturnSales(Builder $query): Builder
    {
        return $query->byType(self::TYPE_RETURN_SALE);
    }

    public function scopeReturnPurchases(Builder $query): Builder
    {
        return $query->byType(self::TYPE_RETURN_PURCHASE);
    }

    public function scopeWithReturns(Builder $query): Builder
    {
        return $query->has('returns');
    }

    public function scopeOriginalTransactions(Builder $query): Builder
    {
        return $query->whereNull('relation_to')
            ->whereHas('transactionType', function ($q) {
                $q->whereIn('code', [self::TYPE_SALE, self::TYPE_PURCHASE]);
            });
    }

    public function scopeRelatedTo(Builder $query, int $transactionId): Builder
    {
        return $query->where('relation_to', $transactionId);
    }

    // ✅ AGREGAR ESTE MÉTODO DESPUÉS DE applySaleReturnRule()
    // ✅ REGLA 1: RESERVAR STOCK PARA VENTAS NUEVAS (MÉTODO PÚBLICO)
    public function reserveStockForSale(): void
    {
        if (!$this->isSale() || !$this->isDeliveryPending()) {
            return;
        }

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            // ✅ REGLA 1: reserved_stock += cantidad (stock no cambia)
            $product->reserveStock($detail->quantity);
            
            \Log::info('Stock reserved for new sale', [
                'transaction_id' => $this->id,
                'product_id' => $product->id,
                'quantity_reserved' => $detail->quantity,
                'stock_after' => $product->fresh()->stock,
                'reserved_after' => $product->fresh()->reserved_stock
            ]);
        }
    }

    // ✅ MÉTODO CORREGIDO: PROCESAR STOCK AL ENTREGAR (REGLA 3)
    private function processStockMovementOnDelivery(): void
    {
        $transactionType = $this->transactionType?->code;

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            switch ($transactionType) {
                case self::TYPE_SALE:
                    // ✅ REGLA 3: reserved_stock -= cantidad, stock -= cantidad
                    $product->confirmSaleDelivery($detail->quantity);
                    
                    \Log::info('Sale delivery confirmed', [
                        'transaction_id' => $this->id,
                        'product_id' => $product->id,
                        'quantity_delivered' => $detail->quantity,
                        'stock_after' => $product->fresh()->stock,
                        'reserved_after' => $product->fresh()->reserved_stock
                    ]);
                    break;

                case self::TYPE_PURCHASE:
                    // ✅ REGLA 6: stock += cantidad (reserved_stock no se toca)
                    $product->processPurchase($detail->quantity);
                    
                    \Log::info('Purchase processed', [
                        'transaction_id' => $this->id,
                        'product_id' => $product->id,
                        'quantity_purchased' => $detail->quantity,
                        'stock_after' => $product->fresh()->stock
                    ]);
                    break;

                case self::TYPE_RETURN_SALE:
                    // ✅ REGLA 4A: stock += cantidad (ya fue entregado)
                    $product->processSaleReturn($detail->quantity, true);
                    
                    \Log::info('Sale return processed (was delivered)', [
                        'transaction_id' => $this->id,
                        'product_id' => $product->id,
                        'quantity_returned' => $detail->quantity,
                        'stock_after' => $product->fresh()->stock
                    ]);
                    break;

                case self::TYPE_RETURN_PURCHASE:
                    // ✅ REGLA 7: stock -= cantidad (devolver compra)
                    $product->cancelPurchase($detail->quantity);
                    
                    \Log::info('Purchase return processed', [
                        'transaction_id' => $this->id,
                        'product_id' => $product->id,
                        'quantity_returned' => $detail->quantity,
                        'stock_after' => $product->fresh()->stock
                    ]);
                    break;
            }
        }
    }

    // ✅ MÉTODO PÚBLICO PARA MARCAR COMO ENTREGADO
    public function markAsDelivered(): void
    {
        DB::transaction(function () {
            if (!$this->canBeDelivered()) {
                throw new \InvalidArgumentException('Esta transacción no puede ser marcada como entregada');
            }

            $this->delivery_status = self::DELIVERY_STATUS_DELIVERED;
            
            // ✅ PROCESAR STOCK AL ENTREGAR
            $this->processStockMovementOnDelivery();
            
            $this->save();
            
            \Log::info('Transaction marked as delivered', [
                'transaction_id' => $this->id,
                'transaction_code' => $this->code,
                'transaction_type' => $this->transactionType?->code
            ]);
        });
    }
}
