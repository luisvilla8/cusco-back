<?php

namespace App\Models;

use App\Rules\ZoneBusinessRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Zone
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $location_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Egress> $egresses
 * @property-read int|null $egresses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductPriceDetail> $productPriceDetails
 * @property-read int|null $product_price_details_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trip> $trips
 * @property-read int|null $trips_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserZone> $userZones
 * @property-read int|null $user_zones_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $usersViaZones
 * @property-read int|null $users_via_zones_count
 * @method static Builder|Zone active()
 * @method static Builder|Zone byCode(string $code)
 * @method static Builder|Zone newModelQuery()
 * @method static Builder|Zone newQuery()
 * @method static Builder|Zone onlyTrashed()
 * @method static Builder|Zone query()
 * @method static Builder|Zone search(string $search)
 * @method static Builder|Zone whereCode($value)
 * @method static Builder|Zone whereCreatedAt($value)
 * @method static Builder|Zone whereDeletedAt($value)
 * @method static Builder|Zone whereDescription($value)
 * @method static Builder|Zone whereId($value)
 * @method static Builder|Zone whereLocationUrl($value)
 * @method static Builder|Zone whereName($value)
 * @method static Builder|Zone whereUpdatedAt($value)
 * @method static Builder|Zone withTrashed()
 * @method static Builder|Zone withoutTrashed()
 * @mixin \Eloquent
 */
class Zone extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'name',
        'code',
        'description',
        'location_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function userZones(): HasMany
    {
        return $this->hasMany(UserZone::class);
    }

    public function usersViaZones(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_zones')
            ->withTimestamps()
            ->withPivot('deleted_at')
            ->wherePivot('deleted_at', null);
    }

    public function productPriceDetails(): HasMany
    {
        return $this->hasMany(ProductPriceDetail::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function egresses(): HasMany
    {
        return $this->hasMany(Egress::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    public function scopeOrderByUsage(Builder $query, string $order = 'desc'): Builder
    {
        return $query->withCount('productPriceDetails')
                     ->orderBy('product_price_details_count', $order);
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function hasUsers(): bool
    {
        // ✅ USAR SOLO userZones() que ya está bien definida
        return $this->userZones()->exists();
    }

    public function hasTransactions(): bool
    {
        return $this->transactions()->exists();
    }

    public function hasTrips(): bool
    {
        return $this->trips()->exists();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasUsers() &&
            !$this->hasTransactions() &&
            !$this->hasTrips();
    }

    public function generateCode(): string
    {
        // Generar código basado en el nombre
        $baseName = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->name), 0, 6));
        $baseName = $baseName ?: 'ZONE';

        // Agregar número secuencial
        $counter = 1;
        do {
            $code = $baseName . str_pad($counter, 2, '0', STR_PAD_LEFT);
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

    public function validateLocationUrl(): bool
    {
        if (empty($this->location_url)) return true;

        // Validar URL válida
        if (filter_var($this->location_url, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Validar formato de coordenadas (lat,lng)
        if (preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $this->location_url)) {
            $coords = explode(',', $this->location_url);
            $lat = (float) $coords[0];
            $lng = (float) $coords[1];

            return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
        }

        return false;
    }

    /**
     * Find zone by code
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
        static::creating(function ($zone) {
            if (empty($zone->code)) {
                $zone->code = $zone->generateCode();
            }

            // Normalizar datos
            $zone->name = ucwords(trim($zone->name));
            $zone->code = strtoupper(trim($zone->code));
        });

        // Validación antes de guardar
        static::saving(function ($zone) {
            // Validar location_url si se proporciona
            if (!empty($zone->location_url) && !$zone->validateLocationUrl()) {
                throw new \InvalidArgumentException('La URL de ubicación no tiene un formato válido. Use una URL completa o coordenadas lat,lng');
            }

            // Validar unicidad de name
            $nameExists = static::where('name', $zone->name)
                ->when($zone->exists, function ($query) use ($zone) {
                    return $query->where('id', '!=', $zone->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($nameExists) {
                throw new \InvalidArgumentException("El nombre '{$zone->name}' ya está en uso");
            }

            // Validar unicidad de code
            $codeExists = static::where('code', $zone->code)
                ->when($zone->exists, function ($query) use ($zone) {
                    return $query->where('id', '!=', $zone->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$zone->code}' ya está en uso");
            }
        });

        // Validación antes de eliminar
        static::deleting(function ($zone) {
            if (!$zone->isForceDeleting() && !$zone->canBeDeleted()) {
                throw new \InvalidArgumentException('No se puede eliminar una zona que tiene usuarios, transacciones o viajes asociados');
            }
        });
    }
}
