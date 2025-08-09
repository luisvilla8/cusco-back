<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\UserZone.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\UserZone
 *
 * @property int $id
 * @property int $user_id
 * @property int $zone_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $assignment_info
 * @property-read string $display_name
 * @property-read bool $has_active_user
 * @property-read bool $has_active_zone
 * @property-read bool $is_active
 * @property-read bool $is_fully_active
 * @property-read string $user_code
 * @property-read string $user_name
 * @property-read string $zone_code
 * @property-read string $zone_name
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Zone $zone
 * @method static Builder|UserZone active()
 * @method static Builder|UserZone byUser(int $userId)
 * @method static Builder|UserZone byUserAndZone(int $userId, int $zoneId)
 * @method static Builder|UserZone byZone(int $zoneId)
 * @method static Builder|UserZone fullyActive()
 * @method static Builder|UserZone newModelQuery()
 * @method static Builder|UserZone newQuery()
 * @method static Builder|UserZone onlyTrashed()
 * @method static Builder|UserZone orderByUser(string $direction = 'asc')
 * @method static Builder|UserZone orderByZone(string $direction = 'asc')
 * @method static Builder|UserZone query()
 * @method static Builder|UserZone search(string $search)
 * @method static Builder|UserZone whereCreatedAt($value)
 * @method static Builder|UserZone whereDeletedAt($value)
 * @method static Builder|UserZone whereId($value)
 * @method static Builder|UserZone whereUpdatedAt($value)
 * @method static Builder|UserZone whereUserId($value)
 * @method static Builder|UserZone whereZoneId($value)
 * @method static Builder|UserZone withActiveUsers()
 * @method static Builder|UserZone withActiveZones()
 * @method static Builder|UserZone withRelations()
 * @method static Builder|UserZone withTrashed()
 * @method static Builder|UserZone withoutTrashed()
 * @mixin \Eloquent
 */
class UserZone extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_zones';

    protected $fillable = [
        'user_id',
        'zone_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    //  RELACIONES
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    //  SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByUserAndZone(Builder $query, int $userId, int $zoneId): Builder
    {
        return $query->where('user_id', $userId)
                    ->where('zone_id', $zoneId);
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'user:id,name,code,email',
            'zone:id,name,code,description'
        ]);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas('user', function ($userQuery) use ($search) {
            $userQuery->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('code', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
        })->orWhereHas('zone', function ($zoneQuery) use ($search) {
            $zoneQuery->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('code', 'LIKE', "%{$search}%");
        });
    }

    public function scopeOrderByUser(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->join('users', 'user_zones.user_id', '=', 'users.id')
                    ->orderBy('users.name', $direction)
                    ->select('user_zones.*');
    }

    public function scopeOrderByZone(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->join('zones', 'user_zones.zone_id', '=', 'zones.id')
                    ->orderBy('zones.name', $direction)
                    ->select('user_zones.*');
    }

    public function scopeWithActiveUsers(Builder $query): Builder
    {
        return $query->whereHas('user', function ($userQuery) {
            $userQuery->whereNull('deleted_at');
        });
    }

    public function scopeWithActiveZones(Builder $query): Builder
    {
        return $query->whereHas('zone', function ($zoneQuery) {
            $zoneQuery->whereNull('deleted_at');
        });
    }

    public function scopeFullyActive(Builder $query): Builder
    {
        return $query->active()
                    ->withActiveUsers()
                    ->withActiveZones();
    }

    //  ACCESSORS
    public function getDisplayNameAttribute(): string
    {
        return "{$this->user?->name} - {$this->zone?->name}";
    }

    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?? 'Usuario no encontrado';
    }

    public function getZoneNameAttribute(): string
    {
        return $this->zone?->name ?? 'Zona no encontrada';
    }

    public function getUserCodeAttribute(): string
    {
        return $this->user?->code ?? '';
    }

    public function getZoneCodeAttribute(): string
    {
        return $this->zone?->code ?? '';
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->deleted_at);
    }

    public function getHasActiveUserAttribute(): bool
    {
        return $this->user && is_null($this->user->deleted_at);
    }

    public function getHasActiveZoneAttribute(): bool
    {
        return $this->zone && is_null($this->zone->deleted_at);
    }

    public function getIsFullyActiveAttribute(): bool
    {
        return $this->is_active && $this->has_active_user && $this->has_active_zone;
    }

    public function getAssignmentInfoAttribute(): string
    {
        $user = $this->user_name;
        $zone = $this->zone_name;
        $status = $this->is_fully_active ? 'Activo' : 'Inactivo';
        
        return "{$user} asignado a {$zone} - {$status}";
    }

    //  MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        // UserZone normalmente siempre se puede eliminar
        // Aquí podrías agregar lógica específica si fuera necesario
        return true;
    }

    public function isValidAssignment(): bool
    {
        return $this->user_id && 
               $this->zone_id && 
               $this->has_active_user && 
               $this->has_active_zone;
    }

    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function belongsToZone(int $zoneId): bool
    {
        return $this->zone_id === $zoneId;
    }

    public function isAssignmentFor(int $userId, int $zoneId): bool
    {
        return $this->user_id === $userId && $this->zone_id === $zoneId;
    }

    //  MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function findByUserAndZone(int $userId, int $zoneId): ?self
    {
        return static::active()
            ->byUserAndZone($userId, $zoneId)
            ->first();
    }

    public static function existsAssignment(int $userId, int $zoneId): bool
    {
        return static::active()
            ->byUserAndZone($userId, $zoneId)
            ->exists();
    }

    public static function getUserZones(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->byUser($userId)
            ->withRelations()
            ->get();
    }

    public static function getZoneUsers(int $zoneId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->byZone($zoneId)
            ->withRelations()
            ->get();
    }

    public static function createAssignment(int $userId, int $zoneId): self
    {
        // Verificar si ya existe la asignación
        $existing = static::findByUserAndZone($userId, $zoneId);
        if ($existing) {
            throw new \InvalidArgumentException('La asignación usuario-zona ya existe');
        }

        return static::create([
            'user_id' => $userId,
            'zone_id' => $zoneId
        ]);
    }

    public static function removeAssignment(int $userId, int $zoneId): bool
    {
        $assignment = static::findByUserAndZone($userId, $zoneId);
        if (!$assignment) {
            return false;
        }

        return $assignment->delete();
    }

    //  VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Validación antes de crear
        static::creating(function ($userZone) {
            // Verificar que el usuario existe y está activo
            $user = User::find($userZone->user_id);
            if (!$user || $user->deleted_at) {
                throw new \InvalidArgumentException('El usuario especificado no existe o está inactivo');
            }

            // Verificar que la zona existe y está activa
            $zone = Zone::find($userZone->zone_id);
            if (!$zone || $zone->deleted_at) {
                throw new \InvalidArgumentException('La zona especificada no existe o está inactiva');
            }
        });

        // Validación antes de guardar
        static::saving(function ($userZone) {
            // Validar unicidad de la combinación user_id + zone_id (solo activos)
            $duplicateExists = static::where('user_id', $userZone->user_id)
                ->where('zone_id', $userZone->zone_id)
                ->when($userZone->exists, function ($query) use ($userZone) {
                    return $query->where('id', '!=', $userZone->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicateExists) {
                throw new \InvalidArgumentException('Ya existe una asignación activa para este usuario y zona');
            }
        });

        // Logging cuando se crea una asignación
        static::created(function ($userZone) {
            Log::info("UserZone assignment created", [
                'user_id' => $userZone->user_id,
                'zone_id' => $userZone->zone_id,
                'user_name' => $userZone->user?->name,
                'zone_name' => $userZone->zone?->name
            ]);
        });

        // Logging cuando se elimina una asignación
        static::deleted(function ($userZone) {
            Log::info("UserZone assignment deleted", [
                'user_id' => $userZone->user_id,
                'zone_id' => $userZone->zone_id,
                'user_name' => $userZone->user?->name,
                'zone_name' => $userZone->zone?->name
            ]);
        });
    }
}