<?php

namespace App\Models;

use App\Rules\ProductBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\Product
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $code
 * @property string|null $barcode
 * @property string|null $image_url
 * @property float $stock
 * @property float $reserved_stock
 * @property float $min_stock
 * @property float $max_stock
 * @property float $cost
 * @property float $price
 * @property int $measure_type_id
 * @property int $product_category_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $display_name
 * @property-read string $stock_status
 * @property-read string $stock_status_color
 * @property-read \App\Models\MeasureType $measureType
 * @property-read \App\Models\ProductCategory $productCategory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductPriceDetail> $productPriceDetails
 * @property-read int|null $product_price_details_count
 * @method static \Illuminate\Database\Eloquent\Builder|Product active()
 * @method static \Illuminate\Database\Eloquent\Builder|Product byCategory($categoryId)
 * @method static \Illuminate\Database\Eloquent\Builder|Product byCode(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder|Product byMeasureType($measureTypeId)
 * @method static \Illuminate\Database\Eloquent\Builder|Product byStockStatus($status)
 * @method static \Illuminate\Database\Eloquent\Builder|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder|Product search($search)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereBarcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereMaxStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereMeasureTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereMinStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereProductCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Product withoutTrashed()
 * @mixin \Eloquent
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'description',
        'code',
        'barcode',
        'image_url',
        'stock',
        'reserved_stock',
        'min_stock',
        'max_stock',
        'cost',
        'price',
        'measure_type_id',
        'product_category_id',
    ];

    protected $casts = [
        'stock' => 'float',
        'reserved_stock' => 'float',
        'min_stock' => 'float',
        'max_stock' => 'float',
        'cost' => 'float',
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ AGREGAR VALIDACIONES EN BOOT
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($product) {
            if (empty($product->code)) {
                $product->code = $product->generateCode();
            }

            // Normalizar datos
            $product->name = ucwords(trim($product->name));
            $product->code = strtoupper(trim($product->code));

            // ✅ VALIDAR QUE STOCK NO SEA NEGATIVO
            if ($product->stock < 0) {
                throw new \InvalidArgumentException('El stock no puede ser negativo');
            }

            if ($product->reserved_stock < 0) {
                throw new \InvalidArgumentException('El stock reservado no puede ser negativo');
            }

            ProductBusinessRules::validateStock($product->toArray());
        });

        static::updating(function ($product) {
            // Normalizar datos en actualización
            $product->name = ucwords(trim($product->name));
            $product->code = strtoupper(trim($product->code));

            // ✅ VALIDAR QUE STOCK NO SEA NEGATIVO
            if ($product->stock < 0) {
                throw new \InvalidArgumentException('El stock no puede ser negativo');
            }

            if ($product->reserved_stock < 0) {
                throw new \InvalidArgumentException('El stock reservado no puede ser negativo');
            }

            // ✅ VALIDAR QUE RESERVED_STOCK NO EXCEDA STOCK TOTAL
            if ($product->reserved_stock > $product->stock) {
                throw new \InvalidArgumentException('El stock reservado no puede exceder el stock total');
            }

            ProductBusinessRules::validateStock($product->toArray());
        });

        // Validar unicidad de code
        static::saving(function ($product) {
            $codeExists = static::where('code', $product->code)
                ->when($product->exists, function ($query) use ($product) {
                    return $query->where('id', '!=', $product->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$product->code}' ya está en uso");
            }
        });

        static::deleting(function ($product) {
            if (!$product->isForceDeleting()) {
                ProductBusinessRules::validateDeletion($product);
            }
        });

        static::forceDeleting(function ($product) {
            ProductBusinessRules::validateForceDeletion($product);
        });
    }

    /**
     * Get the measure type that owns the product
     */
    public function measureType(): BelongsTo
    {
        return $this->belongsTo(MeasureType::class);
    }

    /**
     * Get the product category that owns the product
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * Get all product price details for this product
     */
    public function productPriceDetails(): HasMany
    {
        return $this->hasMany(ProductPriceDetail::class);
    }

    /**
     * Scope para productos activos
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope para búsqueda
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });
        }
        return $query;
    }

    /**
     * Scope para filtrar por categoría
     */
    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId) {
            return $query->where('product_category_id', $categoryId);
        }
        return $query;
    }

    /**
     * Scope para filtrar por tipo de medida
     */
    public function scopeByMeasureType($query, $measureTypeId)
    {
        if ($measureTypeId) {
            return $query->where('measure_type_id', $measureTypeId);
        }
        return $query;
    }

    /**
     * Scope para filtrar por estado de stock
     */
    public function scopeByStockStatus($query, $status)
    {
        switch ($status) {
            case 'SIN_STOCK':
                return $query->where('stock', '<=', 0);
            case 'STOCK_BAJO':
                return $query->whereRaw('stock <= min_stock AND stock > 0');
            case 'STOCK_NORMAL':
                return $query->whereRaw('stock > min_stock AND stock < max_stock');
            case 'STOCK_ALTO':
                return $query->whereRaw('stock >= max_stock');
            default:
                return $query;
        }
    }

    /**
     * Check if product has zone prices
     */
    public function hasZonePrices(): bool
    {
        return $this->productPriceDetails()->exists();
    }

    /**
     * Check if product has transactions
     */
    public function hasTransactions(): bool
    {
        // TODO: Implementar cuando tengas el modelo Transaction/TransactionDetail
        return false;
    }

    /**
     * Check if product has transactions including deleted ones
     */
    public function hasTransactionsInHistory(): bool
    {
        // TODO: Implementar cuando tengas el modelo Transaction/TransactionDetail
        return false;
    }

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    /**
     * Get stock status
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) return 'SIN_STOCK';
        if ($this->stock <= $this->min_stock) return 'STOCK_BAJO';
        if ($this->stock >= $this->max_stock) return 'STOCK_ALTO';
        return 'STOCK_NORMAL';
    }

    /**
     * Get formatted display name
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];

        if ($this->code) {
            $parts[] = "({$this->code})";
        }

        if ($this->measureType?->symbol) {
            $parts[] = "[{$this->measureType->symbol}]";
        }

        return implode(' ', $parts);
    }

    /**
     * Get stock status color for frontend
     */
    public function getStockStatusColorAttribute(): string
    {
        switch ($this->stock_status) {
            case 'SIN_STOCK':
                return 'error';
            case 'STOCK_BAJO':
                return 'warning';
            case 'STOCK_ALTO':
                return 'info';
            case 'STOCK_NORMAL':
                return 'success';
            default:
                return 'default';
        }
    }

    /**
     * Check if stock is sufficient for quantity
     */
    public function hasSufficientStock(float $quantity): bool
    {
        $availableStock = $this->stock - $this->reserved_stock;
        return $availableStock >= $quantity;
    }

    /**
     * Add stock
     */
    public function addStock(float $quantity): void
    {
        $this->increment('stock', $quantity);
    }

    /**
     * Remove stock
     */
    public function removeStock(float $quantity): void
    {
        ProductBusinessRules::validateStockMovement($this, $quantity, 'OUT');
        $this->decrement('stock', $quantity);
    }

    /**
     * Set stock
     */
    public function setStock(float $quantity): void
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('El stock no puede ser negativo');
        }
        $this->update(['stock' => $quantity]);
    }

    /**
     * Generate unique code for product
     */
    public function generateCode(): string
    {
        // Prefijo basado en la categoría
        $categoryCode = $this->productCategory?->code ?? 'PROD';

        // Obtener iniciales del nombre
        $nameParts = explode(' ', trim($this->name));
        $initials = '';

        foreach ($nameParts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        // Asegurar al menos 2 caracteres
        $initials = str_pad($initials, 2, 'X', STR_PAD_RIGHT);

        $baseCode = $categoryCode . $initials;

        // Agregar número secuencial
        $counter = 1;
        do {
            $code = $baseCode . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $exists = static::where('code', $code)
                ->when($this->exists, function ($query) {
                    return $query->where('id', '!=', $this->id);
                })
                ->whereNull('deleted_at')
                ->exists();
            $counter++;
        } while ($exists && $counter <= 999);

        return $code;
    }

    /**
     * Find product by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    // ✅ MÉTODO PRINCIPAL PARA ACTUALIZAR STOCK
    public function updateStock(float $quantity, string $operation = 'ADD'): void
    {
        $operation = strtoupper($operation);

        switch ($operation) {
            case 'ADD':
            case 'IN':
                $this->increment('stock', $quantity);
                break;

            case 'SUBTRACT':
            case 'OUT':
                if ($this->stock < $quantity) {
                    throw new \InvalidArgumentException("Stock insuficiente. Stock actual: {$this->stock}, cantidad solicitada: {$quantity}");
                }
                $this->decrement('stock', $quantity);
                break;

            default:
                throw new \InvalidArgumentException("Operación de stock inválida: {$operation}. Use 'ADD' o 'SUBTRACT'");
        }
    }

    /**
     * Get the available stock considering reserved stock
     */
    public function getAvailableStock(): float
    {
        return max(0, $this->stock - $this->reserved_stock);
    }

    /**
     * Check if there is enough available stock for the requested quantity
     */
    public function hasAvailableStock(float $requestedQuantity = 1): bool
    {
        return $this->available_stock >= $requestedQuantity;
    }

    /**
     * Obtener precio del producto para una zona específica
     */
    public function getPriceForZone(?int $zoneId): float
    {
        if (!$zoneId) {
            return (float) $this->price;
        }

        $zonePriceDetail = $this->productPriceDetails()
            ->where('zone_id', $zoneId)
            ->active()
            ->first();

        return $zonePriceDetail ? (float) $zonePriceDetail->price : (float) $this->price;
    }

    /**
     * Verificar si tiene precio específico para una zona
     */
    public function hasZonePrice(int $zoneId): bool
    {
        return $this->productPriceDetails()
            ->where('zone_id', $zoneId)
            ->active()
            ->exists();
    }

    /**
     * Obtener información completa del precio para una zona
     */
    public function getPriceInfoForZone(?int $zoneId): array
    {
        if (!$zoneId) {
            return [
                'price' => (float) $this->price,
                'source' => 'base_price',
                'has_zone_price' => false
            ];
        }

        $zonePriceDetail = $this->productPriceDetails()
            ->where('zone_id', $zoneId)
            ->active()
            ->first();

        if ($zonePriceDetail) {
            return [
                'price' => (float) $zonePriceDetail->price,
                'source' => 'zone_price',
                'has_zone_price' => true,
                'zone_price_detail_id' => $zonePriceDetail->id
            ];
        }

        return [
            'price' => (float) $this->price,
            'source' => 'base_price_fallback',
            'has_zone_price' => false
        ];
    }

    /**
     * Obtener información completa de stock
     */
    public function getStockInfo(): array
    {
        return [
            'total_stock' => $this->stock,
            'reserved_stock' => $this->reserved_stock,
            'available_stock' => $this->available_stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'stock_status' => $this->getStockStatus(),
            'can_sell' => $this->available_stock > 0,
            'is_low_stock' => $this->available_stock <= $this->min_stock
        ];
    }

    // ✅ ASEGURAR QUE ESTOS MÉTODOS EXISTAN
    public function reserveStock(float $quantity): void
    {
        $this->increment('reserved_stock', $quantity);
        
        \Log::info('Stock reserved for sale', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_reserved' => $quantity,
            'new_reserved_stock' => $this->fresh()->reserved_stock,
            'stock_unchanged' => $this->fresh()->stock
        ]);
    }

    public function confirmSaleDelivery(float $quantity): void
    {
        $this->decrement('reserved_stock', $quantity);
        $this->decrement('stock', $quantity);
        
        \Log::info('Sale delivery confirmed', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_delivered' => $quantity,
            'new_stock' => $this->fresh()->stock,
            'new_reserved_stock' => $this->fresh()->reserved_stock
        ]);
    }

    public function processSaleReturn(float $quantity): void
    {
        $this->increment('stock', $quantity);
        
        \Log::info('Sale return processed', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_returned' => $quantity,
            'new_stock' => $this->fresh()->stock,
            'reserved_stock_unchanged' => $this->fresh()->reserved_stock
        ]);
    }

    public function processPurchase(float $quantity): void
    {
        $this->increment('stock', $quantity);
        
        \Log::info('Purchase processed', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_purchased' => $quantity,
            'new_stock' => $this->fresh()->stock,
            'reserved_stock_unchanged' => $this->fresh()->reserved_stock
        ]);
    }

    public function cancelPurchase(float $quantity): void
    {
        $this->decrement('stock', $quantity);
        
        \Log::info('Purchase cancelled/returned', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_cancelled' => $quantity,
            'new_stock' => $this->fresh()->stock,
            'reserved_stock_unchanged' => $this->fresh()->reserved_stock
        ]);
    }

    public function releaseReservedStock(float $quantity): void
    {
        $currentReserved = $this->reserved_stock;
        $quantityToRelease = min($quantity, $currentReserved);
        
        $this->decrement('reserved_stock', $quantityToRelease);
        
        \Log::info('Reserved stock released', [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'quantity_to_release' => $quantity,
            'quantity_actually_released' => $quantityToRelease,
            'old_reserved_stock' => $currentReserved,
            'new_reserved_stock' => $this->fresh()->reserved_stock,
            'stock_unchanged' => $this->fresh()->stock
        ]);
    }
}
