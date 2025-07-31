<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPriceDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_price_details';

    protected $fillable = [
        'price',
        'code',
        'zone_id',
        'product_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function generateCode(): string
    {
        $productCode = $this->product?->code ?? 'PROD';
        $zoneCode = $this->zone?->code ?? 'ZONE';
        
        return strtoupper($productCode . '-' . $zoneCode);
    }

    /**
     * Find by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($priceDetail) {
            if (empty($priceDetail->code)) {
                $priceDetail->code = 'TEMP-' . uniqid();
            }
        });

        // Actualizar código después de crear
        static::created(function ($priceDetail) {
            if (strpos($priceDetail->code, 'TEMP-') === 0) {
                $priceDetail->update([
                    'code' => $priceDetail->generateCode()
                ]);
            }
        });

        // Validación antes de guardar
        static::saving(function ($priceDetail) {
            // Validar que el precio sea positivo
            if ($priceDetail->price <= 0) {
                throw new \InvalidArgumentException('El precio debe ser mayor a 0');
            }

            // Validar unicidad de code (excluyendo temporales)
            if (!str_starts_with($priceDetail->code, 'TEMP-')) {
                $codeExists = static::where('code', $priceDetail->code)
                    ->when($priceDetail->exists, function ($query) use ($priceDetail) {
                        return $query->where('id', '!=', $priceDetail->id);
                    })
                    ->whereNull('deleted_at')
                    ->exists();

                if ($codeExists) {
                    throw new \InvalidArgumentException("El código '{$priceDetail->code}' ya está en uso");
                }
            }

            // Validar unicidad de zona-producto
            $duplicateExists = static::where('zone_id', $priceDetail->zone_id)
                ->where('product_id', $priceDetail->product_id)
                ->when($priceDetail->exists, function ($query) use ($priceDetail) {
                    return $query->where('id', '!=', $priceDetail->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicateExists) {
                throw new \InvalidArgumentException('Ya existe un precio para este producto en esta zona');
            }
        });
    }
}