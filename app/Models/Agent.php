<?php

namespace App\Models;

use App\Rules\AgentBusinessRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agents';

    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'email',
        'dni',
        'ruc',
        'agent_type_id',
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
        static::creating(function ($agent) {
            if (empty($agent->code)) {
                $agent->code = $agent->generateCode();
            }
            
            // Normalizar datos
            $agent->name = ucwords(trim($agent->name));
            $agent->code = strtoupper(trim($agent->code));
            $agent->email = strtolower(trim($agent->email ?? ''));
            
            AgentBusinessRules::validateAgentData($agent->toArray());
        });

        static::updating(function ($agent) {
            // Normalizar datos en actualización
            $agent->name = ucwords(trim($agent->name));
            $agent->code = strtoupper(trim($agent->code));
            $agent->email = strtolower(trim($agent->email ?? ''));
            
            AgentBusinessRules::validateAgentData($agent->toArray());
        });

        // Validar unicidad de code
        static::saving(function ($agent) {
            $codeExists = static::where('code', $agent->code)
                ->when($agent->exists, function ($query) use ($agent) {
                    return $query->where('id', '!=', $agent->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$agent->code}' ya está en uso");
            }
        });

        static::deleting(function ($agent) {
            if (!$agent->isForceDeleting()) {
                AgentBusinessRules::validateDeletion($agent);
            }
        });

        static::forceDeleting(function ($agent) {
            AgentBusinessRules::validateForceDeletion($agent);
        });
    }

    /**
     * Get the agent type that owns the agent
     */
    public function agentType(): BelongsTo
    {
        return $this->belongsTo(AgentType::class);
    }

    /**
     * Get transactions for this agent
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get trips for this agent
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Scope para agentes activos
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
     * Scope para búsqueda
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('dni', 'like', '%' . $search . '%')
                  ->orWhere('ruc', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }
        return $query;
    }

    /**
     * Scope para filtrar por tipo de agente
     */
    public function scopeByType($query, $agentTypeId)
    {
        if ($agentTypeId) {
            return $query->where('agent_type_id', $agentTypeId);
        }
        return $query;
    }

    /**
     * Scope para clientes (tipos específicos)
     */
    public function scopeClients($query)
    {
        return $query->whereHas('agentType', function ($q) {
            $q->where('name', 'like', '%cliente%');
        });
    }

    /**
     * Scope para proveedores (tipos específicos)
     */
    public function scopeProviders($query)
    {
        return $query->whereHas('agentType', function ($q) {
            $q->where('name', 'like', '%proveedor%');
        });
    }

    /**
     * Check if agent is active
     */
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    /**
     * Get formatted phone
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) return null;
        
        // Formato para teléfonos peruanos
        if (strlen($this->phone) === 9) {
            return substr($this->phone, 0, 3) . ' ' . substr($this->phone, 3, 3) . ' ' . substr($this->phone, 6);
        }
        
        return $this->phone;
    }

    /**
     * Get agent type name
     */
    public function getAgentTypeNameAttribute(): ?string
    {
        return $this->agentType?->name;
    }

    /**
     * Get display name for dropdowns
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = [$this->name];
        
        if ($this->code) {
            $parts[] = "({$this->code})";
        }
        
        if ($this->dni) {
            $parts[] = "DNI: {$this->dni}";
        }
        
        if ($this->ruc) {
            $parts[] = "RUC: {$this->ruc}";
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Generate unique code for agent
     */
    public function generateCode(): string
    {
        // Prefijo basado en el tipo de agente
        $typeCode = $this->agentType?->code ?? 'AGT';
        
        // Obtener iniciales del nombre
        $nameParts = explode(' ', trim($this->name));
        $initials = '';
        
        foreach ($nameParts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        
        // Asegurar al menos 2 caracteres
        $initials = str_pad($initials, 2, 'X', STR_PAD_RIGHT);
        
        $baseCode = $typeCode . $initials;
        
        // Agregar número secuencial
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
     * Find agent by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }
}
