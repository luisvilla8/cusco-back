<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'zone_id',
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

        // Evento antes de eliminar
        static::deleting(function ($user) {
            // Revocar todos los tokens al eliminar
            $user->tokens()->delete();
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
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user's zone
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * Get role name
     */
    public function getRoleName(): ?string
    {
        return $this->role?->name;
    }

    /**
     * Get zone name
     */
    public function getZoneName(): ?string
    {
        return $this->zone?->name;
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
}
