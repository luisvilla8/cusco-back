<?php

namespace App\Models;

use App\Rules\ProductCategoryBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
        'name',
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
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope para categorías activas
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
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
}