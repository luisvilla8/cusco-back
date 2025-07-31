<?php

namespace App\Models;

use App\Rules\ProductBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'min_stock',
        'max_stock',
        'cost',
        'price',
        'measure_type_id',
        'product_category_id',
    ];

    protected $casts = [
        'stock' => 'float',
        'min_stock' => 'float',
        'max_stock' => 'float',
        'cost' => 'float',
        'price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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
            
            ProductBusinessRules::validateStock($product->toArray());
        });

        static::updating(function ($product) {
            // Normalizar datos en actualización
            $product->name = ucwords(trim($product->name));
            $product->code = strtoupper(trim($product->code));
            
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
        return $this->stock >= $quantity;
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
}
