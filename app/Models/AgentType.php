<?php

namespace App\Models;

use App\Rules\AgentTypeBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agent_types';

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
        static::creating(function ($agentType) {
            if (empty($agentType->code)) {
                $agentType->code = $agentType->generateCode();
            }
            
            // Normalizar datos
            $agentType->name = ucwords(trim($agentType->name));
            $agentType->code = strtoupper(trim($agentType->code));
        });

        // Validar unicidad de code
        static::saving(function ($agentType) {
            $codeExists = static::where('code', $agentType->code)
                ->when($agentType->exists, function ($query) use ($agentType) {
                    return $query->where('id', '!=', $agentType->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$agentType->code}' ya está en uso");
            }
        });

        static::deleting(function ($agentType) {
            if (!$agentType->isForceDeleting()) {
                AgentTypeBusinessRules::validateDeletion($agentType);
            }
        });

        static::forceDeleting(function ($agentType) {
            AgentTypeBusinessRules::validateForceDeletion($agentType);
        });
    }

    /**
     * Get the agents for the agent type
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get active agents for the agent type
     */
    public function activeAgents(): HasMany
    {
        return $this->hasMany(Agent::class)->whereNull('deleted_at');
    }

    /**
     * Scope para tipos de agente activos
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
     * Check if agent type has agents
     */
    public function hasAgents(): bool
    {
        return $this->agents()->exists();
    }

    /**
     * Check if agent type has agents including deleted ones
     */
    public function hasAgentsInHistory(): bool
    {
        return $this->agents()->withTrashed()->exists();
    }

    /**
     * Check if agent type is active
     */
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    /**
     * Scope a query to search by name
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
        }
        return $query;
    }

    /**
     * Generate unique code for agent type
     */
    public function generateCode(): string
    {
        // Abreviaciones comunes para tipos de agente
        $abbreviations = [
            'CLIENTE' => 'CLIENT',
            'PROVEEDOR' => 'PROV',
            'DISTRIBUIDOR' => 'DIST',
            'MAYORISTA' => 'MAYOR',
            'MINORISTA' => 'MINOR',
            'EMPRESA' => 'EMP',
            'PERSONA' => 'PERS',
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

        $baseName = $baseName ?: 'TYPE';

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
     * Find agent type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }
}