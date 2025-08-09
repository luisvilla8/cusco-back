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

    const DELIVERY_STATUS_PENDING = 'PENDING';
    const DELIVERY_STATUS_DELIVERED = 'DELIVERED';
    const DELIVERY_STATUS_RETURNED = 'RETURNED';
    const DELIVERY_STATUS_CANCELLED = 'CANCELLED';

    const PAYMENT_STATUS_PENDING = 'PENDING';
    const PAYMENT_STATUS_PARTIAL = 'PARTIAL';
    const PAYMENT_STATUS_PAID = 'PAID';
    const PAYMENT_STATUS_CANCELLED = 'CANCELLED';

    const TYPE_SALE = 'SALE';
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_RETURN_SALE = 'RETURN_SALE';
    const TYPE_RETURN_PURCHASE = 'RETURN_PURCHASE';

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
            $transaction = $transaction->fresh(['transactionDetails.product', 'transactionType']);
            
            if ($transaction && $transaction->transactionDetails->count() > 0) {
                $transaction->applyStockRulesOnCreate();
            }
        });

        static::updating(function ($transaction) {
            if ($transaction->isDirty(['amount_paid', 'total'])) {
                $transaction->updatePaymentStatusAutomatically();
            }
        });

        static::deleting(function ($transaction) {
            if (!$transaction->isForceDeleting()) {
                $transaction->load(['transactionDetails.product', 'transactionType']);
                $transaction->applyStockRulesOnCancel();
            }
        });
    }

    // RELACIONES
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

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'relation_to');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(Transaction::class, 'relation_to');
    }

    // MÉTODOS DE ESTADO
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

    // CAPACIDADES
    public function canBeEdited(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

    public function canBeDelivered(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

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

    public function canBeReturned(): bool
    {
        if ($this->isReturn()) {
            return false;
        }

        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }

        if (!$this->isDelivered() && !$this->isDeliveryReturned()) {
            return false;
        }

        if ($this->getRemainingReturnableAmount() <= 0.01) {
            return false;
        }

        return true;
    }

    public function canBeCancelled(): bool
    {
        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }

        if ($this->trashed()) {
            return false;
        }

        return true;
    }

    // REGLAS DE STOCK
    public function applyStockRulesOnCreate(): void
    {
        $transactionType = $this->transactionType?->code;

        if ($transactionType === self::TYPE_SALE) {
            if ($this->isDeliveryPending()) {
                $this->applySaleCreationRule();
            } elseif ($this->isDelivered()) {
                $this->applySaleCreationRule();
                $this->applySaleDeliveryRule();
            }
        } elseif ($transactionType === self::TYPE_PURCHASE) {
            if ($this->isDelivered()) {
                $this->applyPurchaseDeliveryRule();
            }
        } elseif ($transactionType === self::TYPE_RETURN_SALE) {
            $this->applySaleReturnRule();
        } elseif ($transactionType === self::TYPE_RETURN_PURCHASE) {
            $this->applyPurchaseReturnRule();
        }
    }

    // REGLA: VENTA NUEVA - reserved_stock += cantidad
    public function applySaleCreationRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $product->reserveStock($detail->quantity);
        }
    }

    // REGLA: VENTA ENTREGA - reserved_stock -= cantidad, stock -= cantidad
    public function applySaleDeliveryRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $product->confirmSaleDelivery($detail->quantity);
        }
    }

    // REGLA: COMPRA ENTREGA - stock += cantidad
    public function applyPurchaseDeliveryRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $product->processPurchase($detail->quantity);
        }
    }

    // REGLA: VENTA DEVOLUCIÓN - stock += cantidad
    public function applySaleReturnRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $quantity = abs($detail->quantity);
            $product->processSaleReturn($quantity);
        }
    }

    // REGLA: COMPRA DEVOLUCIÓN - stock -= cantidad
    public function applyPurchaseReturnRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $quantity = abs($detail->quantity);
            $product->cancelPurchase($quantity);
        }
    }

    // MÉTODO PÚBLICO PARA CANCELACIÓN
    public function applyStockRulesOnCancel(): void
    {
        if ($this->isReturn()) {
            $this->revertReturnEffects();
        } else {
            $transactionType = $this->transactionType?->code;
            
            if ($transactionType === self::TYPE_SALE) {
                $this->applySaleCancellationRule();
            } elseif ($transactionType === self::TYPE_PURCHASE) {
                $this->applyPurchaseCancellationRule();
            }
        }
    }

    // REGLA: CANCELACIÓN DE VENTA - reserved_stock -= cantidad
    public function applySaleCancellationRule(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            if ($this->isDelivered()) {
                $product->processSaleReturn($detail->quantity);
            } else {
                $product->releaseReservedStock($detail->quantity);
            }
        }
    }

    // REGLA: CANCELAR COMPRA
    public function applyPurchaseCancellationRule(): void
    {
        if ($this->isDelivered()) {
            foreach ($this->transactionDetails as $detail) {
                $product = $detail->product;
                if (!$product) continue;

                $product->cancelPurchase($detail->quantity);
            }
        }
    }

    // MÉTODO PRIVADO PARA CANCELAR EGRESO DE COMPRA
    private function cancelPurchaseEgress(): void
    {
        try {
            $egress = Egress::where('transaction_id', $this->id)->first();
            
            if ($egress) {
                $egress->delete();
            }
        } catch (\Exception $e) {
            // Continuar sin bloquear la cancelación
        }
    }

    // MÉTODO PÚBLICO: MARCAR COMO ENTREGADO
    public function markAsDelivered(): void
    {
        DB::transaction(function () {
            $transactionType = $this->transactionType?->code;
            
            if ($transactionType === self::TYPE_SALE && $this->isDeliveryPending()) {
                $this->applySaleDeliveryRule();
            } elseif ($transactionType === self::TYPE_PURCHASE && $this->isDeliveryPending()) {
                $this->applyPurchaseDeliveryRule();
            }

            $this->delivery_status = self::DELIVERY_STATUS_DELIVERED;
            $this->save();
        });
    }

    // ACTUALIZAR RESERVAS AL EDITAR VENTA PENDIENTE
    public function updateSaleReservations(array $oldDetails, array $newDetails): void
    {
        if (!$this->isSale() || !$this->isDeliveryPending()) {
            return;
        }

        foreach ($oldDetails as $oldDetail) {
            $product = Product::find($oldDetail['product_id']);
            if (!$product) continue;

            $product->releaseReservedStock($oldDetail['quantity']);
        }

        foreach ($newDetails as $newDetail) {
            $product = Product::find($newDetail['product_id']);
            if (!$product) continue;

            $product->reserveStock($newDetail['quantity']);
        }
    }

    // MÉTODOS HELPER
    public function getActiveReturns()
    {
        return $this->returns()
            ->whereNot('delivery_status', self::DELIVERY_STATUS_CANCELLED)
            ->whereNot('payment_status', self::PAYMENT_STATUS_CANCELLED)
            ->whereNull('deleted_at');
    }

    public function hasActiveReturns(): bool
    {
        return $this->getActiveReturns()->exists();
    }

    public function getActiveReturnsCount(): int
    {
        return $this->getActiveReturns()->count();
    }

    // GENERAR CÓDIGO
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
        $sequential = static::withTrashed()
            ->whereDate('created_at', now())
            ->whereHas('transactionType', function ($q) use ($typeCode) {
                $q->where('code', $typeCode);
            })
            ->count() + 1;

        return "{$prefix}-{$date}-" . str_pad($sequential, 3, '0', STR_PAD_LEFT);
    }

    // PAYMENT STATUS
    public function updatePaymentStatusAutomatically(): void
    {
        if ($this->isPaymentCancelled()) {
            return;
        }

        $newStatus = $this->calculatePaymentStatus();

        if ($this->payment_status !== $newStatus) {
            $this->payment_status = $newStatus;

            if ($this->exists) {
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

    // CÁLCULOS DE DEUDA
    public function calculateDebtInfo(): array
    {
        if ($this->isReturn()) {
            return $this->calculateReturnDebtInfo();
        }

        $originalTotal = (float) $this->total;
        $totalPaid = (float) $this->amount_paid;
        $totalReturned = $this->getTotalReturnedAmount();
        $totalRefunded = $this->getTotalRefundedAmount();
        
        $netTotal = $originalTotal - $totalReturned;
        $netPaid = $totalPaid - $totalRefunded;
        
        $currentDebt = max(0, $netTotal - $netPaid);
        $paymentSurplus = max(0, $netPaid - $netTotal);
        
        return [
            'original_total' => $originalTotal,
            'total_paid' => $totalPaid,
            'total_returned' => $totalReturned,
            'total_refunded' => $totalRefunded,
            'net_total' => $netTotal,
            'net_paid' => $netPaid,
            'current_debt' => $currentDebt,
            'payment_surplus' => $paymentSurplus,
        ];
    }

    private function calculateReturnDebtInfo(): array
    {
        $returnAmount = abs((float) $this->total);
        $refundAmount = (float) $this->amount_paid;
        
        $currentDebt = max(0, $returnAmount - $refundAmount);
        $paymentSurplus = max(0, $refundAmount - $returnAmount);
        
        return [
            'original_total' => 0.0,
            'total_paid' => 0.0,
            'total_returned' => $returnAmount,
            'total_refunded' => $refundAmount,
            'net_total' => $returnAmount,
            'net_paid' => $refundAmount,
            'current_debt' => $currentDebt,
            'payment_surplus' => $paymentSurplus,
        ];
    }

    public function getTotalReturnedAmount(): float
    {
        if ($this->isReturn()) {
            return 0.0;
        }

        return $this->returns()
            ->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($return) {
                return abs((float) $return->total);
            });
    }

    public function getTotalRefundedAmount(): float
    {
        if ($this->isReturn()) {
            return (float) $this->amount_paid;
        }

        return $this->returns()
            ->whereNotIn('delivery_status', [self::DELIVERY_STATUS_CANCELLED])
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($return) {
                return (float) $return->amount_paid;
            });
    }

    public function getRemainingReturnableAmount(): float
    {
        if ($this->isReturn()) {
            return 0.0;
        }

        $originalTotal = (float) $this->total;
        $totalReturned = $this->getTotalReturnedAmount();
        
        return max(0, $originalTotal - $totalReturned);
    }

    public function getCurrentDebt(): float
    {
        return $this->calculateDebtInfo()['current_debt'];
    }

    public function getPaymentSurplus(): float
    {
        return $this->calculateDebtInfo()['payment_surplus'];
    }

    public function getOriginalPrices(): array
    {
        return $this->transactionDetails()
            ->get()
            ->mapWithKeys(function ($detail) {
                return [$detail->product_id => [
                    'price' => (float) $detail->price,
                    'quantity' => (float) $detail->quantity,
                    'product_name' => $detail->product?->name ?? 'Producto no encontrado'
                ]];
            })
            ->toArray();
    }

    // SCOPES
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

    // REVERTIR EFECTOS DE DEVOLUCIÓN
    public function revertReturnEffects(): void
    {
        $returnType = $this->transactionType?->code;
        
        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) continue;

            $quantity = abs($detail->quantity);

            if ($returnType === self::TYPE_RETURN_SALE) {
                $product->cancelPurchase($quantity);
            } elseif ($returnType === self::TYPE_RETURN_PURCHASE) {
                $product->processPurchase($quantity);
            }
        }
        
        $this->revertRefunds();
    }

    // REVERTIR REEMBOLSOS
    public function revertRefunds(): void
    {
        try {
            $refunds = $this->transactionPayments;
            
            if ($refunds->count() > 0) {
                foreach ($refunds as $refund) {
                    $refund->delete();
                }
            }
        } catch (\Exception $e) {
            // Continuar sin bloquear la cancelación
        }
    }

    // CANCELACIÓN UNIFICADA
    public function cancelTransaction(): void
    {
        DB::transaction(function () {
            try {
                if (!$this->canBeCancelled()) {
                    throw new \InvalidArgumentException('Esta transacción no puede ser cancelada en su estado actual');
                }

                // CANCELAR DEVOLUCIONES ACTIVAS EN CASCADA
                if (!$this->isReturn() && $this->hasActiveReturns()) {
                    $activeReturns = $this->getActiveReturns()->get();

                    foreach ($activeReturns as $returnTransaction) {
                        $returnTransaction->load(['transactionDetails.product', 'transactionType']);
                        $returnTransaction->applyStockRulesOnCancel();
                        
                        $returnTransaction->delivery_status = self::DELIVERY_STATUS_CANCELLED;
                        $returnTransaction->payment_status = self::PAYMENT_STATUS_CANCELLED;
                        $returnTransaction->save();
                    }
                }

                // APLICAR REGLAS DE STOCK
                if (!$this->relationLoaded('transactionDetails')) {
                    $this->load(['transactionDetails.product', 'transactionType']);
                }
                
                $this->applyStockRulesOnCancel();

                // CANCELAR EGRESO SI ES COMPRA
                if ($this->isPurchase()) {
                    $this->cancelPurchaseEgress();
                }

                // MARCAR COMO CANCELADA
                $this->delivery_status = self::DELIVERY_STATUS_CANCELLED;
                $this->payment_status = self::PAYMENT_STATUS_CANCELLED;
                $this->save();

                // ACTUALIZAR TRANSACCIÓN ORIGINAL SI ES DEVOLUCIÓN
                if ($this->isReturn() && $this->originalTransaction) {
                    $this->originalTransaction->updatePaymentStatusAutomatically();
                    $this->originalTransaction->save();
                }

            } catch (\Exception $e) {
                throw $e;
            }
        });
    }
}