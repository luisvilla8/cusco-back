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

        static::creating(function ($transactionDetail) {
            // ✅ SOLO VALIDACIONES - NO MANEJO DE STOCK
            if (!$transactionDetail->product_id) {
                throw new \InvalidArgumentException('Product ID is required');
            }
            
            if ($transactionDetail->quantity <= 0) {
                throw new \InvalidArgumentException('Quantity must be greater than 0');
            }
            
            if ($transactionDetail->price < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }
        });

        static::updating(function ($transactionDetail) {
            // ✅ SOLO VALIDACIONES EN UPDATE
            if ($transactionDetail->quantity <= 0) {
                throw new \InvalidArgumentException('Quantity must be greater than 0');
            }
            
            if ($transactionDetail->price < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }
        });

        static::deleting(function ($transactionDetail) {
            // ✅ NO MANEJAR STOCK AQUÍ - SE MANEJA EN TRANSACTION
        });
    }
}
