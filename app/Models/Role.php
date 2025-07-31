<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\Role.php

namespace App\Models;

use App\Rules\RoleBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'code',
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

        // Generar código automáticamente si no se proporciona
        static::creating(function ($role) {
            if (empty($role->code)) {
                $role->code = $role->generateCode();
            }
            
            // Normalizar datos
            $role->name = ucwords(trim($role->name));
            $role->code = strtoupper(trim($role->code));
        });

        // Validar unicidad de code
        static::saving(function ($role) {
            $codeExists = static::where('code', $role->code)
                ->when($role->exists, function ($query) use ($role) {
                    return $query->where('id', '!=', $role->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$role->code}' ya está en uso");
            }
        });

        static::deleting(function ($role) {
            if (!$role->isForceDeleting()) {
                RoleBusinessRules::validateDeletion($role);
            }
        });

        static::forceDeleting(function ($role) {
            RoleBusinessRules::validateForceDeletion($role);
        });
    }

    /**
     * Get all users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope para roles activos
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Check if role has users
     */
    public function hasUsers(): bool
    {
        return $this->users()->exists();
    }

    /**
     * Check if role has users including deleted ones
     */
    public function hasUsersInHistory(): bool
    {
        return $this->users()->withTrashed()->exists();
    }

    /**
     * Generate unique code for role
     */
    public function generateCode(): string
    {
        // Abreviaciones comunes para roles
        $abbreviations = [
            'ADMINISTRADOR' => 'ADMIN',
            'AGENTE' => 'AGENT',
            'USUARIO' => 'USER',
            'SUPERVISOR' => 'SUPER',
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

        $baseName = $baseName ?: 'ROLE';

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
}