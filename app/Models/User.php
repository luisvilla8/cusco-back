<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property int $role_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trip> $activeTrips
 * @property-read int|null $active_trips_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserZone> $allUserZones
 * @property-read int|null $all_user_zones_count
 * @property-read string $display_name
 * @property-read string $full_name
 * @property-read \App\Models\Zone|null $primary_zone
 * @property-read string|null $primary_zone_name
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Role $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Trip> $trips
 * @property-read int|null $trips_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserZone> $userZones
 * @property-read int|null $user_zones_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Zone> $zones
 * @property-read int|null $zones_count
 * @method static \Illuminate\Database\Eloquent\Builder|User active()
 * @method static \Illuminate\Database\Eloquent\Builder|User byCode(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder|User deleted()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User withCommonRelations()
 * @method static \Illuminate\Database\Eloquent\Builder|User withRoles(array $roleNames)
 * @method static \Illuminate\Database\Eloquent\Builder|User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutTrashed()
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = "users";

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($user) {
            if (empty($user->code)) {
                $user->code = $user->generateCode();
            }
            
            // Normalizar datos
            $user->name = ucwords(trim($user->name));
            $user->code = strtoupper(trim($user->code));
            $user->email = strtolower(trim($user->email));
        });

        // Validar unicidad de code
        static::saving(function ($user) {
            $codeExists = static::where('code', $user->code)
                ->when($user->exists, function ($query) use ($user) {
                    return $query->where('id', '!=', $user->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$user->code}' ya está en uso");
            }
        });

        // ✅ EVENTO: ANTES DE SOFT DELETE
        static::deleting(function ($user) {
            Log::info('User being soft deleted', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'force_deleting' => $user->isForceDeleting()
            ]);

            // Revocar todos los tokens al eliminar
            $user->tokens()->delete();

            // ✅ Si es SOFT DELETE, hacer soft delete de UserZones
            if (!$user->isForceDeleting()) {
                // Soft delete de todas las asignaciones de zonas activas
                UserZone::where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->delete(); // Esto hace soft delete

                Log::info('User zones soft deleted', [
                    'user_id' => $user->id,
                    'zones_count' => UserZone::where('user_id', $user->id)->count()
                ]);
            }
        });

        // ✅ EVENTO: ANTES DE FORCE DELETE
        static::forceDeleting(function ($user) {
            Log::info('User being force deleted', [
                'user_id' => $user->id,
                'user_name' => $user->name
            ]);

            // ✅ FORCE DELETE de todas las UserZones (incluidas soft deleted)
            UserZone::withTrashed()
                ->where('user_id', $user->id)
                ->forceDelete();

            Log::info('User zones force deleted', [
                'user_id' => $user->id
            ]);
        });

        // ✅ EVENTO: AL RESTAURAR USUARIO
        static::restoring(function ($user) {
            Log::info('User being restored', [
                'user_id' => $user->id,
                'user_name' => $user->name
            ]);

            // ✅ RESTAURAR UserZones que fueron eliminadas junto con el usuario
            // Solo restaurar las que fueron eliminadas el mismo día o después
            $userDeletedAt = $user->deleted_at;
            
            if ($userDeletedAt) {
                $restoredZones = UserZone::onlyTrashed()
                    ->where('user_id', $user->id)
                    ->where('deleted_at', '>=', $userDeletedAt->subMinutes(5)) // 5 min de tolerancia
                    ->restore();

                Log::info('User zones restored', [
                    'user_id' => $user->id,
                    'restored_count' => $restoredZones
                ]);
            }
        });
    }

    /**
     * Mutator para hashear la contraseña automáticamente
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            if (Hash::needsRehash($value)) {
                $this->attributes['password'] = Hash::make($value);
            } else {
                $this->attributes['password'] = $value;
            }
        }
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Get display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    /**
     * Scope a query to only include active users (no eliminados).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to only include deleted users.
     */
    public function scopeDeleted($query)
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return !is_null($this->email_verified_at) && is_null($this->deleted_at);
    }

    /**
     * Get the user's role (relación uno a muchos)
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * ✅ NUEVA RELACIÓN: Zonas a través de tabla intermedia
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'user_zones')
                    ->whereNull('user_zones.deleted_at') // ✅ IMPORTANTE: Solo activas
                    ->withTimestamps();
    }

    /**
     * ✅ RELACIÓN: UserZones activas
     */
    public function userZones(): HasMany
    {
        return $this->hasMany(UserZone::class)->whereNull('deleted_at');
    }

    /**
     * ✅ RELACIÓN: TODAS las UserZones (incluidas eliminadas)
     */
    public function allUserZones(): HasMany
    {
        return $this->hasMany(UserZone::class)->withTrashed();
    }

    /**
     * ✅ HELPER: Obtener primera zona (para compatibilidad)
     */
    public function getPrimaryZoneAttribute(): ?Zone
    {
        return $this->zones()->first();
    }

    /**
     * ✅ HELPER: Obtener nombre de primera zona
     */
    public function getPrimaryZoneNameAttribute(): ?string
    {
        return $this->primary_zone?->name;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleName): bool
    {
        // ✅ CARGAR RELACIÓN SI NO ESTÁ CARGADA
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * ✅ VERIFICAR SI EL USUARIO TIENE ALGUNO DE LOS ROLES - CORREGIDO
     */
    public function hasAnyRole(array $roles): bool
    {
        // Verificar que el usuario tenga un rol asignado
        if (!$this->role_id) {
            return false;
        }
        
        // ✅ CARGAR RELACIÓN SI NO ESTÁ CARGADA
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }
        
        // Verificar que el rol exista y esté en la lista
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * ✅ MÉTODO AUXILIAR: Obtener nombre del rol
     */
    public function getRoleName(): ?string
    {
        if (!$this->role) {
            $this->load('role');
        }
        return $this->role?->name;
    }

    /**
     * ✅ MÉTODO AUXILIAR: Verificar si es administrador
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Administrador') || $this->hasRole('Super Admin');
    }

    /**
     * ✅ MÉTODO AUXILIAR: Verificar si es vendedor
     */
    public function isVendedor(): bool
    {
        return $this->hasRole('Vendedor');
    }

    /**
     * ✅ VERIFICAR SI EL USUARIO TIENE ACCESO A UNA ZONA
     */
    public function hasZone(int $zoneId): bool
    {
        return $this->zones()->where('zone_id', $zoneId)->exists();
    }

    /**
     * ✅ ASIGNAR zona al usuario
     */
    public function assignToZone(int $zoneId): bool
    {
        if ($this->hasZone($zoneId)) {
            return false; // Ya está asignado
        }

        UserZone::create([
            'user_id' => $this->id,
            'zone_id' => $zoneId
        ]);

        return true;
    }

    /**
     * ✅ QUITAR zona del usuario
     */
    public function removeFromZone(int $zoneId): bool
    {
        return UserZone::where('user_id', $this->id)
                       ->where('zone_id', $zoneId)
                       ->delete() > 0;
    }

    /**
     * Generate unique code for user
     */
    public function generateCode(): string
    {
        // Extract initials from name
        $nameParts = explode(' ', trim($this->name));
        $initials = '';
        
        foreach ($nameParts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        
        // Ensure at least 2 characters
        $initials = str_pad($initials, 2, 'U', STR_PAD_RIGHT);
        
        // Add role prefix if available
        $roleCode = $this->role?->code ?? 'USR';
        $baseCode = $roleCode . $initials;
        
        // Add sequential number
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
     * Find user by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    /**
     * ✅ HELPER: Verificar si el usuario está siendo force deleted
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting ?? false;
    }

    /**
     * ✅ NUEVA RELACIÓN: Trips creados por el usuario
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * ✅ NUEVA RELACIÓN: Trips activos del usuario
     */
    public function activeTrips(): HasMany
    {
        return $this->hasMany(Trip::class)->whereNull('deleted_at');
    }

    /**
     * ✅ SCOPE: Usuarios con roles específicos
     */
    public function scopeWithRoles($query, array $roleNames)
    {
        return $query->whereHas('role', function ($roleQuery) use ($roleNames) {
            $roleQuery->whereIn('name', $roleNames);
        });
    }

    /**
     * ✅ SCOPE: Cargar con relaciones comunes
     */
    public function scopeWithCommonRelations($query)
    {
        return $query->with(['role:id,name,code', 'zones:id,name,code']);
    }

    /**
     * ✅ MÉTODO AUXILIAR: Información de usuario para logging
     */
    public function getLoggingInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->getRoleName(),
            'code' => $this->code,
        ];
    }

    /**
     * ✅ MÉTODO AUXILIAR: Verificar si puede asignar trips a otros usuarios
     */
    public function canAssignTripsToOthers(): bool
    {
        return $this->hasAnyRole(['Administrador', 'Super Admin']);
    }

    /**
     * ✅ MÉTODO AUXILIAR: Verificar si solo puede crear trips para sí mismo
     */
    public function canOnlyCreateOwnTrips(): bool
    {
        return $this->hasRole('Vendedor');
    }
}
