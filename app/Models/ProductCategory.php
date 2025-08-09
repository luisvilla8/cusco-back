<?php

namespace App\Models;

use App\Rules\ProductCategoryBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\ProductCategory
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $display_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory active()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory byCode(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductCategory withoutTrashed()
 * @mixin \Eloquent
 */
class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'code',        //  AGREGAR code al fillable
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        //  AGREGAR generación automática de código
        static::creating(function ($productCategory) {
            if (empty($productCategory->code)) {
                $productCategory->code = $productCategory->generateCode();
            }
            
            // Normalizar datos
            $productCategory->name = ucwords(trim($productCategory->name));
            $productCategory->code = strtoupper(trim($productCategory->code));
        });

        static::updating(function ($productCategory) {
            // Normalizar datos en actualización
            $productCategory->name = ucwords(trim($productCategory->name));
            $productCategory->code = strtoupper(trim($productCategory->code));
        });

        //  AGREGAR validación de unicidad de code
        static::saving(function ($productCategory) {
            $codeExists = static::where('code', $productCategory->code)
                ->when($productCategory->exists, function ($query) use ($productCategory) {
                    return $query->where('id', '!=', $productCategory->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$productCategory->code}' ya está en uso");
            }
        });

        static::deleting(function ($productCategory) {
            if (!$productCategory->isForceDeleting()) {
                ProductCategoryBusinessRules::validateDeletion($productCategory);
            }
        });

        static::forceDeleting(function ($productCategory) {
            ProductCategoryBusinessRules::validateForceDeletion($productCategory);
        });
    }

    /**
     * Get all products in this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id'); //  CORREGIR FK
    }

    /**
     * Scope para categorías activas
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
     * Check if category has products
     */
    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    /**
     * Check if category has products including deleted ones
     */
    public function hasProductsInHistory(): bool
    {
        return $this->products()->withTrashed()->exists();
    }

    /**
     * Generate unique code for product category
     */
    public function generateCode(): string
    {
        // Abreviaciones comunes para categorías
        $abbreviations = [
            'BEBIDAS' => 'BEB',
            'COMESTIBLES' => 'COMEST',
            'LIMPIEZA' => 'LIMP',
            'LACTEOS' => 'LACT',
            'CARNES' => 'CARN',
            'FRUTAS' => 'FRUT',
            'VERDURAS' => 'VERD',
            'PANADERIA' => 'PAN',
            'SNACKS' => 'SNACK',
            'DULCES' => 'DULC',
            'CEREALES' => 'CER',
            'ENLATADOS' => 'ENLAT',
            'CONDIMENTOS' => 'COND',
        ];

        $baseName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->name));
        
        // Buscar abreviación conocida
        foreach ($abbreviations as $search => $abbrev) {
            if (stripos($this->name, $search) !== false) {
                $baseName = $abbrev;
                break;
            }
        }

        // Si no encontró abreviación, usar primeras 6 letras
        if (strlen($baseName) > 6) {
            $baseName = substr($baseName, 0, 6);
        }

        $baseName = $baseName ?: 'CAT';

        // Agregar número secuencial si es necesario
        $counter = 1;
        $originalBase = $baseName;
        
        do {
            $code = $counter === 1 ? $baseName : $originalBase . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $exists = static::where('code', $code)
                ->when($this->exists, function ($query) {
                    return $query->where('id', '!=', $this->id);
                })
                ->whereNull('deleted_at')
                ->exists();
            $counter++;
        } while ($exists && $counter <= 99);

        return $code;
    }

    /**
     * Find product category by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }
}