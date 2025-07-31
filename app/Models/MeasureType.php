<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\MeasureType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeasureType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'measure_types';

    protected $fillable = [
        'name',
        'symbol',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeWithProductsCount(Builder $query): Builder
    {
        return $query->withCount('products');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('symbol', 'LIKE', "%{$search}%");
        });
    }

    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where('name', 'LIKE', "%{$name}%");
    }

    public function scopeBySymbol(Builder $query, string $symbol): Builder
    {
        return $query->where('symbol', 'LIKE', "%{$symbol}%");
    }

    // ✅ ACCESSORS
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->symbol})";
    }

    public function getProductsCountAttribute()
    {
        return $this->products_count ?? $this->products()->count();
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function canBeDeleted(): bool
    {
        return $this->products()->count() === 0;
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Validación antes de eliminar
        static::deleting(function ($measureType) {
            if ($measureType->hasProducts()) {
                throw new \InvalidArgumentException('No se puede eliminar un tipo de medida con productos asociados');
            }
        });

        // Validación antes de crear/actualizar para evitar duplicados
        static::saving(function ($measureType) {
            // Convertir name y symbol a formato estándar
            $measureType->name = ucfirst(trim($measureType->name));
            $measureType->symbol = strtoupper(trim($measureType->symbol));

            // Validar unicidad de name (excluyendo el registro actual si es update)
            $nameExists = static::where('name', $measureType->name)
                ->when($measureType->exists, function ($query) use ($measureType) {
                    return $query->where('id', '!=', $measureType->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($nameExists) {
                throw new \InvalidArgumentException("El nombre '{$measureType->name}' ya está en uso");
            }

            // Validar unicidad de symbol (excluyendo el registro actual si es update)
            $symbolExists = static::where('symbol', $measureType->symbol)
                ->when($measureType->exists, function ($query) use ($measureType) {
                    return $query->where('id', '!=', $measureType->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($symbolExists) {
                throw new \InvalidArgumentException("El símbolo '{$measureType->symbol}' ya está en uso");
            }
        });
    }
}