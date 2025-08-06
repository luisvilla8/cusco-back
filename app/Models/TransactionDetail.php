<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\TransactionDetail
 *
 * @property int $id
 * @property int $product_id
 * @property int $transaction_id
 * @property string $price
 * @property string $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read float $cost_difference
 * @property-read string $display_name
 * @property-read string $formatted_price
 * @property-read string $formatted_quantity
 * @property-read string $formatted_subtotal
 * @property-read bool $is_discounted
 * @property-read bool $is_premium_price
 * @property-read bool $is_profitable
 * @property-read float $price_difference_from_base
 * @property-read string $product_code
 * @property-read string $product_name
 * @property-read float $profit_margin
 * @property-read float $profit_per_unit
 * @property-read float $subtotal
 * @property-read float $total_profit
 * @property-read string $transaction_code
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Transaction $transaction
 * @method static Builder|TransactionDetail active()
 * @method static Builder|TransactionDetail byProduct(int $productId)
 * @method static Builder|TransactionDetail byProductAndTransaction(int $productId, int $transactionId)
 * @method static Builder|TransactionDetail byTransaction(int $transactionId)
 * @method static Builder|TransactionDetail highestQuantities(int $limit = 10)
 * @method static Builder|TransactionDetail highestSubtotals(int $limit = 10)
 * @method static Builder|TransactionDetail newModelQuery()
 * @method static Builder|TransactionDetail newQuery()
 * @method static Builder|TransactionDetail onlyTrashed()
 * @method static Builder|TransactionDetail orderByPrice(string $direction = 'desc')
 * @method static Builder|TransactionDetail orderByQuantity(string $direction = 'desc')
 * @method static Builder|TransactionDetail orderBySubtotal(string $direction = 'desc')
 * @method static Builder|TransactionDetail priceRange(?float $minPrice = null, ?float $maxPrice = null)
 * @method static Builder|TransactionDetail quantityRange(?float $minQuantity = null, ?float $maxQuantity = null)
 * @method static Builder|TransactionDetail query()
 * @method static Builder|TransactionDetail search(string $search)
 * @method static Builder|TransactionDetail subtotalRange(?float $minSubtotal = null, ?float $maxSubtotal = null)
 * @method static Builder|TransactionDetail whereCreatedAt($value)
 * @method static Builder|TransactionDetail whereDeletedAt($value)
 * @method static Builder|TransactionDetail whereId($value)
 * @method static Builder|TransactionDetail wherePrice($value)
 * @method static Builder|TransactionDetail whereProductId($value)
 * @method static Builder|TransactionDetail whereQuantity($value)
 * @method static Builder|TransactionDetail whereTransactionId($value)
 * @method static Builder|TransactionDetail whereUpdatedAt($value)
 * @method static Builder|TransactionDetail withRelations()
 * @method static Builder|TransactionDetail withTrashed()
 * @method static Builder|TransactionDetail withoutTrashed()
 * @mixin \Eloquent
 */
class TransactionDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaction_details';

    protected $fillable = [
        'product_id',
        'transaction_id',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeByProductAndTransaction(Builder $query, int $productId, int $transactionId): Builder
    {
        return $query->where('product_id', $productId)
                    ->where('transaction_id', $transactionId);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas('product', function ($productQuery) use ($search) {
            $productQuery->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('code', 'LIKE', "%{$search}%")
                         ->orWhere('barcode', 'LIKE', "%{$search}%");
        })->orWhereHas('transaction', function ($transactionQuery) use ($search) {
            $transactionQuery->where('code', 'LIKE', "%{$search}%");
        });
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'product:id,name,code,cost,price,measure_type_id',
            'product.measureType:id,name,symbol',
            'transaction:id,code,date,total'
        ]);
    }

    public function scopeQuantityRange(Builder $query, float $minQuantity = null, float $maxQuantity = null): Builder
    {
        if ($minQuantity !== null) {
            $query->where('quantity', '>=', $minQuantity);
        }
        if ($maxQuantity !== null) {
            $query->where('quantity', '<=', $maxQuantity);
        }
        return $query;
    }

    public function scopePriceRange(Builder $query, float $minPrice = null, float $maxPrice = null): Builder
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        return $query;
    }

    public function scopeSubtotalRange(Builder $query, float $minSubtotal = null, float $maxSubtotal = null): Builder
    {
        if ($minSubtotal !== null) {
            $query->whereRaw('(price * quantity) >= ?', [$minSubtotal]);
        }
        if ($maxSubtotal !== null) {
            $query->whereRaw('(price * quantity) <= ?', [$maxSubtotal]);
        }
        return $query;
    }

    public function scopeOrderBySubtotal(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderByRaw("(price * quantity) {$direction}");
    }

    public function scopeOrderByQuantity(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('quantity', $direction);
    }

    public function scopeOrderByPrice(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('price', $direction);
    }

    public function scopeHighestQuantities(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByQuantity('desc')->limit($limit);
    }

    public function scopeHighestSubtotals(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBySubtotal('desc')->limit($limit);
    }

    // ✅ ACCESSORS
    public function getSubtotalAttribute(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    public function getFormattedPriceAttribute(): string
    {
        return "S/ " . number_format($this->price, 2);
    }

    public function getFormattedQuantityAttribute(): string
    {
        $symbol = $this->product?->measureType?->symbol ?? 'Und';
        return number_format($this->quantity, 2) . " {$symbol}";
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return "S/ " . number_format($this->subtotal, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->product?->name} - {$this->formatted_quantity}";
    }

    public function getProductNameAttribute(): string
    {
        return $this->product?->name ?? 'Producto no encontrado';
    }

    public function getProductCodeAttribute(): string
    {
        return $this->product?->code ?? '';
    }

    public function getTransactionCodeAttribute(): string
    {
        return $this->transaction?->code ?? '';
    }

    public function getCostDifferenceAttribute(): float
    {
        $productCost = $this->product?->cost ?? 0;
        return $this->price - $productCost;
    }

    public function getProfitPerUnitAttribute(): float
    {
        return $this->cost_difference;
    }

    public function getTotalProfitAttribute(): float
    {
        return round($this->profit_per_unit * $this->quantity, 2);
    }

    public function getProfitMarginAttribute(): float
    {
        $productCost = $this->product?->cost ?? 0;
        if ($productCost <= 0) return 0;
        return round((($this->price - $productCost) / $productCost) * 100, 2);
    }

    public function getIsProfitableAttribute(): bool
    {
        return $this->profit_per_unit > 0;
    }

    public function getPriceDifferenceFromBaseAttribute(): float
    {
        $basePrice = $this->product?->price ?? 0;
        return $this->price - $basePrice;
    }

    public function getIsDiscountedAttribute(): bool
    {
        return $this->price_difference_from_base < 0;
    }

    public function getIsPremiumPriceAttribute(): bool
    {
        return $this->price_difference_from_base > 0;
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        // TransactionDetail puede eliminarse si la transacción lo permite
        // Aquí podrías agregar lógica específica basada en el estado de la transacción
        return true;
    }

    public function isValidDetail(): bool
    {
        return $this->product_id && 
               $this->transaction_id && 
               $this->price > 0 && 
               $this->quantity > 0;
    }

    public function belongsToProduct(int $productId): bool
    {
        return $this->product_id === $productId;
    }

    public function belongsToTransaction(int $transactionId): bool
    {
        return $this->transaction_id === $transactionId;
    }

    public function updateStock(string $operation = 'subtract'): void
    {
        if (!$this->product) {
            throw new \InvalidArgumentException('Product must be loaded to update stock');
        }

        switch (strtolower($operation)) {
            case 'subtract':
            case 'out':
                $this->product->updateStock($this->quantity, 'SUBTRACT');
                break;
            case 'add':
            case 'in':
                $this->product->updateStock($this->quantity, 'ADD');
                break;
            default:
                throw new \InvalidArgumentException("Invalid stock operation: {$operation}");
        }
    }

    public function calculateSubtotal(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    public function isQuantityValid(): bool
    {
        return $this->quantity > 0;
    }

    public function isPriceValid(): bool
    {
        return $this->price > 0;
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function getTotalByProduct(int $productId): float
    {
        return static::active()
            ->byProduct($productId)
            ->get()
            ->sum('subtotal');
    }

    public static function getTotalByTransaction(int $transactionId): float
    {
        return static::active()
            ->byTransaction($transactionId)
            ->get()
            ->sum('subtotal');
    }

    public static function getQuantitySoldByProduct(int $productId): float
    {
        return static::active()
            ->byProduct($productId)
            ->sum('quantity');
    }

    public static function createDetail(int $productId, int $transactionId, float $price, float $quantity): self
    {
        // Verificar que no exista ya un detalle para el mismo producto en la misma transacción
        $existing = static::active()
            ->byProductAndTransaction($productId, $transactionId)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Ya existe un detalle para este producto en esta transacción');
        }

        return static::create([
            'product_id' => $productId,
            'transaction_id' => $transactionId,
            'price' => $price,
            'quantity' => $quantity
        ]);
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // ✅ ELIMINAR VALIDACIÓN DE DUPLICADOS EN UPDATE
        static::creating(function ($detail) {
            // Verificar que el producto existe
            if (!Product::find($detail->product_id)) {
                throw new \InvalidArgumentException('El producto especificado no existe.');
            }

            // Verificar que la transacción existe
            if (!Transaction::find($detail->transaction_id)) {
                throw new \InvalidArgumentException('La transacción especificada no existe.');
            }

            // ✅ REMOVER VALIDACIÓN DE DUPLICADOS - PERMITIR MÚLTIPLES DETALLES DEL MISMO PRODUCTO
            // No validar unicidad porque en updates se recrean los detalles
        });

        // Validación antes de guardar
        static::saving(function ($detail) {
            if ($detail->price <= 0) {
                throw new \InvalidArgumentException('El precio debe ser mayor a 0.');
            }

            if ($detail->quantity <= 0) {
                throw new \InvalidArgumentException('La cantidad debe ser mayor a 0.');
            }
        });

        // ✅ EVENTOS DE STOCK (mantener los existentes pero mejorar logging)
        static::created(function ($detail) {
            Log::info('TransactionDetail created', [
                'detail_id' => $detail->id,
                'transaction_id' => $detail->transaction_id,
                'product_id' => $detail->product_id,
                'price' => $detail->price,
                'quantity' => $detail->quantity
            ]);
            
            $detail->handleStockOnCreate();
        });

        static::deleted(function ($detail) {
            Log::info('TransactionDetail deleted', [
                'detail_id' => $detail->id,
                'transaction_id' => $detail->transaction_id,
                'product_id' => $detail->product_id
            ]);
            
            $detail->handleStockOnDelete();
        });

        static::updated(function ($detail) {
            if ($detail->isDirty('quantity')) {
                Log::info('TransactionDetail quantity updated', [
                    'detail_id' => $detail->id,
                    'old_quantity' => $detail->getOriginal('quantity'),
                    'new_quantity' => $detail->quantity
                ]);
                
                $detail->handleStockOnUpdate();
            }
        });
    }

    // ✅ MÉTODO CORREGIDO PARA MANEJAR STOCK AL CREAR
    public function handleStockOnCreate(): void
    {
        $transaction = $this->transaction;
        $product = $this->product;
        
        if (!$transaction || !$product) {
            Log::warning('Cannot handle stock: missing transaction or product', [
                'detail_id' => $this->id,
                'transaction_id' => $this->transaction_id,
                'product_id' => $this->product_id
            ]);
            return;
        }

        $transactionType = $transaction->transactionType?->code;
        
        Log::info('Handling stock on create', [
            'detail_id' => $this->id,
            'transaction_type' => $transactionType,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'delivery_status' => $transaction->delivery_status,
            'stock_before' => $product->stock
        ]);

        // ✅ MANEJAR SEGÚN TIPO DE TRANSACCIÓN
        switch ($transactionType) {
            case 'SALE':
                // VENTA: Reducir stock cuando se entrega
                if ($transaction->delivery_status === 'DELIVERED') {
                    $this->reduceStock();
                    Log::info('Stock reduced for delivered sale', [
                        'product_id' => $this->product_id,
                        'quantity_reduced' => $this->quantity,
                        'new_stock' => $product->fresh()->stock
                    ]);
                } else {
                    Log::info('Sale not delivered yet, stock not affected', [
                        'delivery_status' => $transaction->delivery_status
                    ]);
                }
                break;

            case 'PURCHASE':
                // COMPRA: Aumentar stock cuando se recibe
                if ($transaction->delivery_status === 'DELIVERED') {
                    $this->addStock();
                    Log::info('Stock increased for received purchase', [
                        'product_id' => $this->product_id,
                        'quantity_added' => $this->quantity,
                        'new_stock' => $product->fresh()->stock
                    ]);
                } else {
                    Log::info('Purchase not received yet, stock not affected', [
                        'delivery_status' => $transaction->delivery_status
                    ]);
                }
                break;

            case 'RETURN_SALE':
                // DEVOLUCIÓN DE VENTA: Restaurar stock inmediatamente
                $this->addStock();
                Log::info('Stock restored for sale return', [
                    'product_id' => $this->product_id,
                    'quantity_restored' => $this->quantity,
                    'new_stock' => $product->fresh()->stock
                ]);
                break;

            case 'RETURN_PURCHASE':
                // DEVOLUCIÓN DE COMPRA: Reducir stock inmediatamente
                $this->reduceStock();
                Log::info('Stock reduced for purchase return', [
                    'product_id' => $this->product_id,
                    'quantity_reduced' => $this->quantity,
                    'new_stock' => $product->fresh()->stock
                ]);
                break;

            default:
                Log::info('No stock handling for transaction type', [
                    'transaction_type' => $transactionType
                ]);
                break;
        }
    }

    // ✅ MÉTODO CORREGIDO PARA MANEJAR STOCK AL ELIMINAR
    public function handleStockOnDelete(): void
    {
        $transaction = $this->transaction;
        $product = $this->product;
        
        if (!$transaction || !$product) {
            Log::warning('Cannot handle stock on delete: missing transaction or product', [
                'detail_id' => $this->id
            ]);
            return;
        }

        $transactionType = $transaction->transactionType?->code;
        
        Log::info('Handling stock on delete', [
            'detail_id' => $this->id,
            'transaction_type' => $transactionType,
            'delivery_status' => $transaction->delivery_status,
            'stock_before' => $product->stock
        ]);

        // ✅ REVERTIR OPERACIÓN SEGÚN TIPO DE TRANSACCIÓN
        switch ($transactionType) {
            case 'SALE':
                if ($transaction->delivery_status === 'DELIVERED') {
                    // Restaurar stock que se había reducido
                    $this->addStock();
                    Log::info('Stock restored after deleting delivered sale detail');
                }
                break;

            case 'PURCHASE':
                if ($transaction->delivery_status === 'DELIVERED') {
                    // Reducir stock que se había aumentado
                    $this->reduceStock();
                    Log::info('Stock reduced after deleting received purchase detail');
                }
                break;

            case 'RETURN_SALE':
                // Revertir la restauración: reducir stock
                $this->reduceStock();
                Log::info('Stock reduced after deleting sale return detail');
                break;

            case 'RETURN_PURCHASE':
                // Revertir la reducción: restaurar stock
                $this->addStock();
                Log::info('Stock restored after deleting purchase return detail');
                break;

            default:
                Log::info('No stock reversion needed for transaction type', [
                    'transaction_type' => $transactionType
                ]);
                break;
        }
    }

    // ✅ NUEVO MÉTODO PARA MANEJAR STOCK AL ACTUALIZAR
    public function handleStockOnUpdate(): void
    {
        $transaction = $this->transaction;
        $product = $this->product;
        
        if (!$transaction || !$product) {
            return;
        }

        $transactionType = $transaction->transactionType?->code;
        $oldQuantity = $this->getOriginal('quantity');
        $newQuantity = $this->quantity;
        $quantityDifference = $newQuantity - $oldQuantity;

        Log::info('Handling stock on quantity update', [
            'detail_id' => $this->id,
            'transaction_type' => $transactionType,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'difference' => $quantityDifference
        ]);

        if ($quantityDifference == 0) {
            return; // No hay cambio en cantidad
        }

        // ✅ APLICAR DIFERENCIA SEGÚN TIPO DE TRANSACCIÓN
        switch ($transactionType) {
            case 'SALE':
                if ($transaction->delivery_status === 'DELIVERED') {
                    // Si se aumentó cantidad, reducir más stock. Si se redujo, restaurar stock.
                    $this->adjustStock(-$quantityDifference);
                }
                break;

            case 'PURCHASE':
                if ($transaction->delivery_status === 'DELIVERED') {
                    // Si se aumentó cantidad, aumentar más stock. Si se redujo, reducir stock.
                    $this->adjustStock($quantityDifference);
                }
                break;

            case 'RETURN_SALE':
                // Ajustar restauración de stock
                $this->adjustStock($quantityDifference);
                break;

            case 'RETURN_PURCHASE':
                // Ajustar reducción de stock
                $this->adjustStock(-$quantityDifference);
                break;
        }
    }

    // ✅ MÉTODOS AUXILIARES PARA MANEJO DE STOCK
    private function addStock(): void
    {
        $product = $this->product;
        if (!$product) return;

        $product->updateStock($this->quantity, 'ADD');
        
        Log::info('Stock increased for product', [
            'product_id' => $this->product_id,
            'product_code' => $product->code,
            'quantity_added' => $this->quantity,
            'new_stock' => $product->fresh()->stock
        ]);
    }

    private function reduceStock(): void
    {
        $product = $this->product;
        if (!$product) return;

        $product->updateStock($this->quantity, 'SUBTRACT');
        
        Log::info('Stock reduced for product', [
            'product_id' => $this->product_id,
            'product_code' => $product->code,
            'quantity_reduced' => $this->quantity,
            'new_stock' => $product->fresh()->stock
        ]);
    }

    private function adjustStock(float $quantityDifference): void
    {
        if ($quantityDifference == 0) return;

        $product = $this->product;
        if (!$product) return;

        $operation = $quantityDifference > 0 ? 'ADD' : 'SUBTRACT';
        $product->updateStock(abs($quantityDifference), $operation);
        
        Log::info('Stock adjusted for product', [
            'product_id' => $this->product_id,
            'product_code' => $product->code,
            'quantity_difference' => $quantityDifference,
            'operation' => $operation,
            'new_stock' => $product->fresh()->stock
        ]);
    }

    // ✅ MÉTODO PARA VERIFICAR SI ES TRANSACCIÓN DE VENTA
    private function isSaleTransaction(): bool
    {
        return $this->transaction?->transactionType?->code === 'SALE';
    }

    // ✅ MÉTODO PARA CONFIRMAR RESERVA (CONVERTIR RESERVA EN VENTA REAL)
    public function confirmReservation(): void
    {
        if (!$this->isSaleTransaction()) {
            return;
        }
        
        $product = $this->product;
        
        // Reducir stock real y liberar reserva
        if ($product->stock >= $this->quantity && $product->reserved_stock >= $this->quantity) {
            $product->decrement('stock', $this->quantity);
            $product->decrement('reserved_stock', $this->quantity);
            
            Log::info('Reservation confirmed', [
                'product_id' => $this->product_id,
                'quantity' => $this->quantity,
                'remaining_stock' => $product->stock,
                'remaining_reserved' => $product->reserved_stock
            ]);
        } else {
            Log::error('Cannot confirm reservation - insufficient stock or reserved stock', [
                'product_id' => $this->product_id,
                'quantity_needed' => $this->quantity,
                'available_stock' => $product->stock,
                'reserved_stock' => $product->reserved_stock
            ]);
            
            throw new \InvalidArgumentException('No se puede confirmar la reserva por stock insuficiente');
        }
    }
}
