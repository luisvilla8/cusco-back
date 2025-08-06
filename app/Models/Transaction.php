<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Models\Egress;
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
        'relation_to',              // ✅ NUEVO CAMPO
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
            
            // ✅ CALCULAR PAYMENT_STATUS AL CREAR TRANSACCIÓN
            if (is_null($transaction->payment_status) || $transaction->payment_status === self::PAYMENT_STATUS_PENDING) {
                $transaction->updatePaymentStatusAutomatically();
            }
        });

        // ✅ ACTUALIZACIÓN AUTOMÁTICA DE PAYMENT_STATUS EN UPDATE
        static::updating(function ($transaction) {
            // Solo actualizar payment_status si cambió amount_paid o total
            if ($transaction->isDirty(['amount_paid', 'total'])) {
                $transaction->updatePaymentStatusAutomatically();
            }
        });

        // ✅ SOFT DELETE CASCADA
        static::deleting(function ($transaction) {
            if (!$transaction->isForceDeleting()) {
                // Soft delete de detalles y pagos relacionados
                $transaction->transactionDetails()->delete();
                $transaction->transactionPayments()->delete();
                
                // Eliminar egreso asociado si es compra
                if ($transaction->isPurchase()) {
                    $egress = Egress::where('transaction_id', $transaction->id)->first();
                    if ($egress) {
                        $egress->delete();
                    }
                }
            }
        });
    }

    // ✅ RELACIONES ACTUALIZADAS
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

    // ✅ NUEVAS RELACIONES PARA DEVOLUCIONES
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'relation_to');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(Transaction::class, 'relation_to');
    }

    // ✅ MÉTODOS DE TIPO DE TRANSACCIÓN
    public function isSale(): bool
    {
        return $this->transactionType?->code === self::TYPE_SALE;
    }

    public function isPurchase(): bool
    {
        return $this->transactionType?->code === self::TYPE_PURCHASE;
    }

    public function isReturnSale(): bool
    {
        return $this->transactionType?->code === self::TYPE_RETURN_SALE;
    }

    public function isReturnPurchase(): bool
    {
        return $this->transactionType?->code === self::TYPE_RETURN_PURCHASE;
    }

    public function isReturn(): bool
    {
        return $this->isReturnSale() || $this->isReturnPurchase();
    }

    public function isOriginalTransaction(): bool
    {
        return $this->isSale() || $this->isPurchase();
    }

    // ✅ MÉTODOS PARA DEVOLUCIONES
    public function canBeReturned(): bool
    {
        // Solo se pueden devolver transacciones entregadas
        if (!$this->isDelivered()) {
            return false;
        }
        
        // Solo ventas y compras pueden ser devueltas
        if (!$this->isOriginalTransaction()) {
            return false;
        }
        
        return true;
    }

    public function hasReturns(): bool
    {
        return $this->returns()->exists();
    }

    // ✅ CORREGIR MÉTODO PARA OBTENER MONTO TOTAL DEVUELTO
    public function getTotalReturnedAmount(): float
    {
        // ✅ OBTENER VALOR ABSOLUTO DE DEVOLUCIONES ENTREGADAS/PROCESADAS
        return $this->returns()
            ->whereIn('delivery_status', [
                self::DELIVERY_STATUS_DELIVERED, 
                self::DELIVERY_STATUS_RETURNED
            ])
            ->get()
            ->sum(function ($return) {
                // Los montos de devolución son negativos, convertir a positivo
                return abs($return->total);
            });
    }

    public function getRemainingReturnableAmount(): float
    {
        return max(0, $this->total - $this->getTotalReturnedAmount());
    }

    public function canReturnAmount(float $amount): bool
    {
        return $amount <= $this->getRemainingReturnableAmount();
    }

    public function getReturnedProductQuantity(int $productId): float
    {
        return $this->returns()
            ->whereHas('transactionDetails', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->where('delivery_status', self::DELIVERY_STATUS_DELIVERED)
            ->get()
            ->flatMap->transactionDetails
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    public function getReturnableQuantityForProduct(int $productId): float
    {
        $originalQuantity = $this->transactionDetails()
            ->where('product_id', $productId)
            ->sum('quantity');
            
        $returnedQuantity = $this->getReturnedProductQuantity($productId);
        
        return max(0, $originalQuantity - $returnedQuantity);
    }

    public function canReturnProduct(int $productId, float $quantity): bool
    {
        return $quantity <= $this->getReturnableQuantityForProduct($productId);
    }

    // ✅ MÉTODOS DE STOCK ACTUALIZADOS PARA DEVOLUCIONES
    public function markAsDelivered(): void
    {
        DB::transaction(function () {
            $oldStatus = $this->delivery_status;
            
            // Actualizar status
            $this->update(['delivery_status' => self::DELIVERY_STATUS_DELIVERED]);
            
            // ✅ PROCESAR STOCK SEGÚN TIPO DE TRANSACCIÓN SOLO SI CAMBIA DE PENDING A DELIVERED
            if ($oldStatus === self::DELIVERY_STATUS_PENDING) {
                $this->processStockMovement();
            }
            
            Log::info('Transaction marked as delivered', [
                'transaction_id' => $this->id,
                'transaction_type' => $this->transactionType?->code,
                'old_status' => $oldStatus,
                'new_status' => self::DELIVERY_STATUS_DELIVERED
            ]);
        });
    }

    // ✅ MÉTODO ACTUALIZADO PARA MANEJAR DEVOLUCIONES
    private function processStockMovement(): void
    {
        $transactionType = $this->transactionType?->code;
        
        Log::info('Processing stock movement for delivered transaction', [
            'transaction_id' => $this->id,
            'transaction_type' => $transactionType,
            'details_count' => $this->transactionDetails->count()
        ]);

        foreach ($this->transactionDetails as $detail) {
            $product = $detail->product;
            if (!$product) {
                Log::warning('Product not found for detail', [
                    'detail_id' => $detail->id,
                    'product_id' => $detail->product_id
                ]);
                continue;
            }

            $operation = $this->getStockOperationForType($transactionType);
            
            if ($operation) {
                $product->updateStock($detail->quantity, $operation);
                
                Log::info('Stock updated for product', [
                    'product_id' => $product->id,
                    'transaction_type' => $transactionType,
                    'operation' => $operation,
                    'quantity' => $detail->quantity,
                    'new_stock' => $product->fresh()->stock
                ]);
            } else {
                Log::info('No stock operation needed for transaction type', [
                    'transaction_type' => $transactionType
                ]);
            }
        }
    }

    // ✅ MÉTODO ACTUALIZADO PARA INCLUIR DEVOLUCIONES
    private function getStockOperationForType(string $transactionType): ?string
    {
        return match($transactionType) {
            'SALE' => 'SUBTRACT',           // Venta: reducir stock
            'PURCHASE' => 'ADD',            // Compra: aumentar stock
            'RETURN_SALE' => 'ADD',         // ✅ Devolución de venta: restaurar stock
            'RETURN_PURCHASE' => 'SUBTRACT', // ✅ Devolución de compra: reducir stock
            default => null                 // Otros tipos: sin operación
        };
    }

    // ✅ SCOPES ACTUALIZADOS
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

    // ✅ MÉTODO generateCode ACTUALIZADO
    public function generateCode(): string
    {
        $typeCode = $this->transactionType?->code ?? 'TXN';
        
        // ✅ CÓDIGOS ESPECÍFICOS PARA DEVOLUCIONES
        $prefix = match($typeCode) {
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

    // ✅ MÉTODO CORREGIDO PARA ACTUALIZACIÓN AUTOMÁTICA
    public function updatePaymentStatusAutomatically(): void
    {
        // No cambiar si ya está cancelado
        if ($this->isPaymentCancelled()) {
            Log::info('Payment status not updated - already cancelled', [
                'transaction_id' => $this->id
            ]);
            return;
        }

        $newStatus = $this->calculatePaymentStatus();
        
        Log::info('Calculating payment status', [
            'transaction_id' => $this->id,
            'current_payment_status' => $this->payment_status,
            'calculated_payment_status' => $newStatus,
            'total' => $this->total,
            'amount_paid' => $this->amount_paid,
            'is_return' => $this->isReturn()
        ]);
        
        if ($this->payment_status !== $newStatus) {
            $oldStatus = $this->payment_status;
            $this->payment_status = $newStatus;
            
            // ✅ NO USAR save() AQUÍ PARA EVITAR RECURSIÓN
            // Si estamos en el proceso de creación, no guardar aún
            if (!$this->exists) {
                Log::info('Payment status will be set on creation', [
                    'transaction_id' => $this->id ?? 'creating',
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            } else {
                // Solo actualizar en base de datos si ya existe
                static::withoutEvents(function () use ($newStatus) {
                    $this->update(['payment_status' => $newStatus]);
                });
            }
            
            Log::info('Payment status updated automatically', [
                'transaction_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'total' => $this->total,
                'amount_paid' => $this->amount_paid
            ]);
        } else {
            Log::info('Payment status unchanged', [
                'transaction_id' => $this->id,
                'payment_status' => $this->payment_status
            ]);
        }
    }

    // ✅ MÉTODO MEJORADO: CALCULAR PAYMENT_STATUS
    public function calculatePaymentStatus(): string
    {
        // ✅ PARA DEVOLUCIONES: SIEMPRE PAID
        if ($this->isReturn()) {
            Log::info('Return transaction - payment status PAID', [
                'transaction_id' => $this->id,
                'transaction_type' => $this->transactionType?->code
            ]);
            return self::PAYMENT_STATUS_PAID;
        }
        
        // ✅ PARA TRANSACCIONES CANCELADAS: MANTENER CANCELLED
        if ($this->isDeliveryCancelled()) {
            Log::info('Cancelled transaction - payment status CANCELLED', [
                'transaction_id' => $this->id
            ]);
            return self::PAYMENT_STATUS_CANCELLED;
        }
        
        $total = (float) $this->total;
        $amountPaid = (float) $this->amount_paid;
        
        Log::info('Calculating payment status for original transaction', [
            'transaction_id' => $this->id,
            'total' => $total,
            'amount_paid' => $amountPaid,
            'difference' => $total - $amountPaid
        ]);
        
        // ✅ LÓGICA SIMPLE BASADA EN total vs amount_paid
        if ($amountPaid <= 0.01) {
            // No ha pagado nada
            Log::info('No payment detected - status PENDING', [
                'transaction_id' => $this->id,
                'amount_paid' => $amountPaid
            ]);
            return self::PAYMENT_STATUS_PENDING;
        }
        
        if ($amountPaid >= ($total - 0.01)) {
            // Pagó todo (con tolerancia de 1 centavo)
            Log::info('Full payment detected - status PAID', [
                'transaction_id' => $this->id,
                'amount_paid' => $amountPaid,
                'total' => $total
            ]);
            return self::PAYMENT_STATUS_PAID;
        }
        
        // Pagó algo pero no todo
        Log::info('Partial payment detected - status PARTIAL', [
            'transaction_id' => $this->id,
            'amount_paid' => $amountPaid,
            'total' => $total,
            'remaining' => $total - $amountPaid
        ]);
        return self::PAYMENT_STATUS_PARTIAL;
    }

    // ✅ MÉTODO CORREGIDO: CALCULAR DEUDA CON INFORMACIÓN CORRECTA PARA DEVOLUCIONES
    public function calculateDebtInfo(): array
    {
        // ✅ PARA DEVOLUCIONES: MOSTRAR SUS PROPIOS VALORES
        if ($this->isReturn()) {
            $returnedAmount = abs($this->total); // Monto de productos devueltos
            $refundedAmount = $this->transactionPayments->sum('amount_paid'); // Dinero reembolsado
            
            return [
                'original_total' => $returnedAmount,
                'total_returned' => $returnedAmount,     // ✅ MOSTRAR MONTO DEVUELTO
                'total_refunded' => $refundedAmount,     // ✅ MOSTRAR MONTO REEMBOLSADO
                'net_total' => $returnedAmount,
                'amount_paid' => 0.0, // amount_paid siempre 0 para devoluciones en transaction
                'current_debt' => 0.0,
                'payment_surplus' => 0.0,
                'debt_status' => 'none',
                'calculation_formula' => "N/A (devolución de S/ {$returnedAmount})"
            ];
        }
        
        // Para transacciones originales (VENTA o COMPRA)
        $originalTotal = (float) $this->total;
        $amountPaid = (float) $this->amount_paid;
        
        // ✅ OBTENER DEVOLUCIONES PROCESADAS
        $returns = $this->returns()
            ->whereIn('delivery_status', [
                self::DELIVERY_STATUS_DELIVERED, 
                self::DELIVERY_STATUS_RETURNED
            ])
            ->get();

        // ✅ SEPARAR: PRODUCTOS DEVUELTOS VS DINERO REEMBOLSADO
        $totalReturnedProducts = (float) $returns->sum(function ($return) {
            return abs($return->total); // Monto de productos devueltos (positivo)
        });
        
        $totalRefunded = (float) $returns->sum(function ($return) {
            return $return->transactionPayments->sum('amount_paid'); // Dinero realmente reembolsado
        });
        
        // ✅ FÓRMULA CORREGIDA: 
        // deuda = total_original - amount_paid + total_productos_devueltos
        $currentDebt = $originalTotal - $amountPaid + $totalReturnedProducts;
        
        // ✅ TOTAL NETO: lo que debe pagar considerando devoluciones
        $netTotal = $originalTotal + $totalReturnedProducts;
        
        Log::info('=== DEBT CALCULATION FOR ORIGINAL TRANSACTION ===', [
            'transaction_id' => $this->id,
            'original_total' => $originalTotal,
            'amount_paid' => $amountPaid,
            'returns_count' => $returns->count(),
            'total_returned_products' => $totalReturnedProducts, // ✅ PRODUCTOS DEVUELTOS
            'total_refunded_money' => $totalRefunded,           // ✅ DINERO REEMBOLSADO
            'formula' => "debt = {$originalTotal} - {$amountPaid} + {$totalReturnedProducts} = {$currentDebt}",
            'net_total' => $netTotal
        ]);
        
        if ($currentDebt > 0.01) {
            return [
                'original_total' => $originalTotal,
                'total_returned' => $totalReturnedProducts,  // ✅ PRODUCTOS DEVUELTOS
                'total_refunded' => $totalRefunded,          // ✅ DINERO REEMBOLSADO
                'net_total' => $netTotal,
                'amount_paid' => $amountPaid,
                'current_debt' => $currentDebt,
                'payment_surplus' => 0.0,
                'debt_status' => 'debtor',
                'calculation_formula' => "total({$originalTotal}) - paid({$amountPaid}) + returned({$totalReturnedProducts}) = {$currentDebt}"
            ];
        } else {
            $surplus = abs($currentDebt);
            return [
                'original_total' => $originalTotal,
                'total_returned' => $totalReturnedProducts,  // ✅ PRODUCTOS DEVUELTOS
                'total_refunded' => $totalRefunded,          // ✅ DINERO REEMBOLSADO
                'net_total' => $netTotal,
                'amount_paid' => $amountPaid,
                'current_debt' => 0.0,
                'payment_surplus' => $surplus,
                'debt_status' => $currentDebt < -0.01 ? 'surplus' : 'paid',
                'calculation_formula' => "total({$originalTotal}) - paid({$amountPaid}) + returned({$totalReturnedProducts}) = {$currentDebt}"
            ];
        }
    }

    // ✅ MÉTODO CORREGIDO: OBTENER DEUDA ACTUAL
    public function getCurrentDebt(): float
    {
        return $this->calculateDebtInfo()['current_debt'];
    }

    // ✅ MÉTODO CORREGIDO: OBTENER SURPLUS
    public function getPaymentSurplus(): float
    {
        return $this->calculateDebtInfo()['payment_surplus'];
    }

    // ✅ CORREGIR MÉTODO: markAsReturned
    public function markAsReturned(): void
    {
        DB::transaction(function () {
            $oldDeliveryStatus = $this->delivery_status;
            
            // ✅ CAMBIAR DELIVERY_STATUS A RETURNED
            $this->delivery_status = self::DELIVERY_STATUS_RETURNED;
            
            // ✅ PARA DEVOLUCIONES: PAYMENT_STATUS SIEMPRE PAID
            if ($this->isReturn()) {
                $this->payment_status = self::PAYMENT_STATUS_PAID;
            }
            
            $this->save();
            
            // ✅ SI ES DEVOLUCIÓN, ACTUALIZAR PAYMENT_STATUS DE LA TRANSACCIÓN ORIGINAL
            if ($this->isReturn() && $this->relation_to) {
                $originalTransaction = Transaction::find($this->relation_to);
                if ($originalTransaction) {
                    $originalTransaction->updatePaymentStatusAutomatically();
                    $originalTransaction->save();
                }
            }
            
            Log::info('Transaction marked as returned', [
                'transaction_id' => $this->id,
                'transaction_type' => $this->transactionType?->code,
                'old_delivery_status' => $oldDeliveryStatus,
                'new_delivery_status' => $this->delivery_status,
                'payment_status' => $this->payment_status,
                'is_return' => $this->isReturn(),
                'relation_to' => $this->relation_to
            ]);
        });
    }

    public function cancelTransaction(): void
    {
        DB::transaction(function () {
            $oldDeliveryStatus = $this->delivery_status;
            
            $this->update([
                'delivery_status' => self::DELIVERY_STATUS_CANCELLED,
                'payment_status' => self::PAYMENT_STATUS_CANCELLED
            ]);
            
            if ($oldDeliveryStatus === self::DELIVERY_STATUS_PENDING && $this->isSale()) {
                $this->releaseReservedStock();
            }
            
            if ($oldDeliveryStatus === self::DELIVERY_STATUS_DELIVERED) {
                $this->revertStockMovement();
            }
            
            Log::info('Transaction cancelled', [
                'transaction_id' => $this->id,
                'old_delivery_status' => $oldDeliveryStatus
            ]);
        });
    }

    // ✅ MANTENER MÉTODOS EXISTENTES PARA PAGOS
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

    // ✅ MÉTODOS PRIVADOS EXISTENTES (mantener todos: revertStockMovement, releaseReservedStock, etc.)
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
            
            Log::info('Stock reverted for product', [
                'product_id' => $product->id,
                'transaction_type' => $transactionType,
                'quantity' => $detail->quantity,
                'new_stock' => $product->fresh()->stock
            ]);
        }
    }

    private function releaseReservedStock(): void
    {
        foreach ($this->transactionDetails as $detail) {
            $product = Product::find($detail->product_id);
            if ($product) {
                $product->releaseReservedStock($detail->quantity);
            }
        }
    }

    // ✅ MÉTODO PARA OBTENER PRECIO ORIGINAL DE UN PRODUCTO
    public function getOriginalPriceForProduct(int $productId): ?float
    {
        $detail = $this->transactionDetails()
            ->where('product_id', $productId)
            ->first();
            
        return $detail ? (float) $detail->price : null;
    }

    // ✅ MÉTODO PARA OBTENER TODOS LOS PRECIOS ORIGINALES
    public function getOriginalPrices(): array
    {
        return $this->transactionDetails()
            ->get()
            ->mapWithKeys(function ($detail) {
                return [$detail->product_id => (float) $detail->price];
            })
            ->toArray();
    }

    // ✅ MÉTODO PARA VALIDAR PRECIOS DE DEVOLUCIÓN
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


    // ✅ AGREGAR MÉTODO HELPER PARA VERIFICAR SI HAY SURPLUS
    public function hasPaymentSurplus(): bool
    {
        return $this->getPaymentSurplus() > 0;
    }

    // ✅ NUEVO MÉTODO: CALCULAR MONTOS DE DEVOLUCIÓN CORREGIDO
    public function calculateReturnAmounts(float $returnAmount): array
    {
        $currentDebt = $this->getCurrentDebt();
        $paymentSurplus = $this->getPaymentSurplus();
        
        $refundAmount = 0;
        $debtCompensation = 0;
        
        if ($currentDebt > 0) {
            // Cliente tiene deuda: compensar deuda, no reembolsar
            $debtCompensation = min($returnAmount, $currentDebt);
            $refundAmount = max(0, $returnAmount - $currentDebt);
        } else {
            // Cliente ya pagó completo o de más: reembolsar
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

    // MÉTODOS DE DELIVERY STATUS
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

    // MÉTODOS DE PAYMENT STATUS
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

    // ✅ MÉTODOS COMBINADOS ÚTILES
    public function canBeEdited(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

    public function canBeDelivered(): bool
    {
        return $this->isDeliveryPending() && !$this->isDeliveryCancelled();
    }

    public function canBeCancelled(): bool
    {
        return !$this->isDeliveryCancelled() && !$this->isPaymentCancelled();
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

    // ✅ MÉTODOS PARA VALIDACIONES DE NEGOCIO
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
        // Reglas de transición de estados
        $currentDelivery = $this->delivery_status;
        $currentPayment = $this->payment_status;
        
        // No se puede cambiar desde CANCELLED
        if ($this->isDeliveryCancelled() || $this->isPaymentCancelled()) {
            return false;
        }
        
        // Validaciones específicas de transición
        switch ($newDeliveryStatus) {
            case self::DELIVERY_STATUS_DELIVERED:
                return $this->isDeliveryPending();
                
            case self::DELIVERY_STATUS_RETURNED:
                return $this->isDelivered() || $this->isDeliveryPending();
                
            case self::DELIVERY_STATUS_CANCELLED:
                return !$this->isDelivered(); // Solo si no ha sido entregado
                
            default:
                return true;
        }
    }

    // ✅ MÉTODOS PARA SCOPES (COMPATIBILIDAD)
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
}
