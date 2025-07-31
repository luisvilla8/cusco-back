<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        // Validación antes de crear
        static::creating(function ($detail) {
            // Verificar que el producto existe
            if (!Product::find($detail->product_id)) {
                throw new \InvalidArgumentException('El producto especificado no existe');
            }

            // Verificar que la transacción existe
            if (!Transaction::find($detail->transaction_id)) {
                throw new \InvalidArgumentException('La transacción especificada no existe');
            }
        });

        // Validación antes de guardar
        static::saving(function ($detail) {
            // Validar que price y quantity sean positivos
            if ($detail->price <= 0) {
                throw new \InvalidArgumentException('El precio debe ser mayor a 0');
            }

            if ($detail->quantity <= 0) {
                throw new \InvalidArgumentException('La cantidad debe ser mayor a 0');
            }

            // Validar unicidad de product_id + transaction_id
            $duplicateExists = static::where('product_id', $detail->product_id)
                ->where('transaction_id', $detail->transaction_id)
                ->when($detail->exists, function ($query) use ($detail) {
                    return $query->where('id', '!=', $detail->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicateExists) {
                throw new \InvalidArgumentException('Ya existe un detalle para este producto en esta transacción');
            }
        });

        // Actualizar stock después de crear (para ventas)
        static::created(function ($detail) {
            // Si la transacción es una venta, reducir stock
            if ($detail->transaction && $detail->transaction->transaction_type_id) {
                $transactionType = $detail->transaction->transactionType;
                if ($transactionType && $transactionType->code === 'SALE') {
                    $detail->updateStock('subtract');
                }
            }
        });

        // Logging cuando se crea un detalle
        static::created(function ($detail) {
            \Log::info("TransactionDetail created", [
                'product_id' => $detail->product_id,
                'transaction_id' => $detail->transaction_id,
                'quantity' => $detail->quantity,
                'price' => $detail->price,
                'subtotal' => $detail->subtotal
            ]);
        });

        // Restaurar stock si se elimina (para ventas)
        static::deleted(function ($detail) {
            if ($detail->transaction && $detail->transaction->transaction_type_id) {
                $transactionType = $detail->transaction->transactionType;
                if ($transactionType && $transactionType->code === 'SALE') {
                    $detail->updateStock('add');
                }
            }
        });
    }
}
