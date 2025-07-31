<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\Egress.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Egress extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'egresses';

    protected $fillable = [
        'code',
        'name',
        'description',
        'amount',
        'date',
        'agent_id',
        'trip_id',
        'zone_id',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('code', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhereHas('agent', function ($agentQuery) use ($search) {
                  $agentQuery->where('name', 'LIKE', "%{$search}%")
                             ->orWhere('code', 'LIKE', "%{$search}%");
              });
        });
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeByAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeByTrip(Builder $query, int $tripId): Builder
    {
        return $query->where('trip_id', $tripId);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByMonth(Builder $query, int $month, int $year = null): Builder
    {
        $year = $year ?? now()->year;
        return $query->whereMonth('date', $month)
                    ->whereYear('date', $year);
    }

    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    public function scopeAmountRange(Builder $query, float $minAmount = null, float $maxAmount = null): Builder
    {
        if ($minAmount !== null) {
            $query->where('amount', '>=', $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('amount', '<=', $maxAmount);
        }
        return $query;
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'agent:id,name,code',
            'trip:id,name,code',
            'zone:id,name,code',
            'transaction:id,code'
        ]);
    }

    public function scopeOrderByAmount(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('amount', $direction);
    }

    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('date', $direction);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    public function scopeThisYear(Builder $query): Builder
    {
        return $query->whereYear('date', now()->year);
    }

    // ✅ ACCESSORS
    public function getFormattedAmountAttribute(): string
    {
        return "S/ " . number_format($this->amount, 2);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('d/m/Y');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getEgressTypeAttribute(): string
    {
        if ($this->trip_id) return 'viaje';
        if ($this->transaction_id) return 'transaccion';
        if ($this->agent_id) return 'proveedor';
        if ($this->zone_id) return 'zona';
        return 'general';
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->date->isAfter(now()->subDays(7));
    }

    public function getIsThisMonthAttribute(): bool
    {
        return $this->date->month === now()->month && 
               $this->date->year === now()->year;
    }

    public function getRelatedEntityNameAttribute(): ?string
    {
        if ($this->agent) return $this->agent->name;
        if ($this->trip) return $this->trip->name;
        if ($this->zone) return $this->zone->name;
        if ($this->transaction) return "Transacción #{$this->transaction->code}";
        return null;
    }

    public function getContextInfoAttribute(): string
    {
        $parts = [];
        
        if ($this->agent) $parts[] = "Agente: {$this->agent->name}";
        if ($this->trip) $parts[] = "Viaje: {$this->trip->name}";
        if ($this->zone) $parts[] = "Zona: {$this->zone->name}";
        if ($this->transaction) $parts[] = "Transacción: {$this->transaction->code}";
        
        return implode(' | ', $parts) ?: 'Egreso general';
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        // Los egresos normalmente siempre se pueden eliminar (soft delete)
        // Aquí podrías agregar lógica específica si fuera necesario
        return true;
    }

    public function generateCode(): string
    {
        // Prefijo basado en el tipo de egreso
        $prefix = 'EGR';
        
        if ($this->trip_id) $prefix = 'EGR-TRIP';
        elseif ($this->transaction_id) $prefix = 'EGR-TRANS';
        elseif ($this->agent_id) $prefix = 'EGR-AGENT';
        elseif ($this->zone_id) $prefix = 'EGR-ZONE';
        
        // Fecha en formato YYMMDD
        $dateCode = ($this->date ?? now())->format('ymd');
        
        // Número secuencial para el día
        $dailyCount = static::whereDate('date', $this->date ?? now()->toDateString())
            ->whereNull('deleted_at')
            ->count() + 1;
        
        $sequentialNumber = str_pad($dailyCount, 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$dateCode}-{$sequentialNumber}";
    }

    public function isRelatedTo(string $entityType, int $entityId): bool
    {
        return match($entityType) {
            'agent' => $this->agent_id === $entityId,
            'trip' => $this->trip_id === $entityId,
            'zone' => $this->zone_id === $entityId,
            'transaction' => $this->transaction_id === $entityId,
            default => false
        };
    }

    public function hasContext(): bool
    {
        return $this->agent_id || $this->trip_id || $this->zone_id || $this->transaction_id;
    }

    public function isTripExpense(): bool
    {
        return !is_null($this->trip_id);
    }

    public function isTransactionRelated(): bool
    {
        return !is_null($this->transaction_id);
    }

    public function isAgentRelated(): bool
    {
        return !is_null($this->agent_id);
    }

    public function isZoneSpecific(): bool
    {
        return !is_null($this->zone_id);
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function getTotalByDateRange(string $startDate, string $endDate): float
    {
        return static::active()
            ->dateRange($startDate, $endDate)
            ->sum('amount');
    }

    public static function getTotalByTrip(int $tripId): float
    {
        return static::active()
            ->byTrip($tripId)
            ->sum('amount');
    }

    public static function getTotalByAgent(int $agentId): float
    {
        return static::active()
            ->byAgent($agentId)
            ->sum('amount');
    }

    public static function getTotalThisMonth(): float
    {
        return static::active()
            ->thisMonth()
            ->sum('amount');
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($egress) {
            if (empty($egress->code)) {
                $egress->code = $egress->generateCode();
            }
            
            // Normalizar datos
            $egress->name = trim($egress->name);
            $egress->code = strtoupper(trim($egress->code));
            
            // Si no se proporciona fecha, usar la fecha actual
            if (empty($egress->date)) {
                $egress->date = now()->toDateString();
            }
        });

        // Validación antes de guardar
        static::saving(function ($egress) {
            // Validar que el monto sea positivo
            if ($egress->amount <= 0) {
                throw new \InvalidArgumentException('El monto del egreso debe ser mayor a 0');
            }

            // Validar unicidad de code
            $codeExists = static::where('code', $egress->code)
                ->when($egress->exists, function ($query) use ($egress) {
                    return $query->where('id', '!=', $egress->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$egress->code}' ya está en uso");
            }

            // Validar que la fecha no sea futura (opcional)
            if ($egress->date > now()->toDateString()) {
                throw new \InvalidArgumentException('La fecha del egreso no puede ser futura');
            }

            // Validar que los IDs de relaciones existan (si se proporcionan)
            if ($egress->agent_id && !Agent::find($egress->agent_id)) {
                throw new \InvalidArgumentException('El agente especificado no existe');
            }
            
            if ($egress->trip_id && !Trip::find($egress->trip_id)) {
                throw new \InvalidArgumentException('El viaje especificado no existe');
            }
            
            if ($egress->zone_id && !Zone::find($egress->zone_id)) {
                throw new \InvalidArgumentException('La zona especificada no existe');
            }
            
            if ($egress->transaction_id && !Transaction::find($egress->transaction_id)) {
                throw new \InvalidArgumentException('La transacción especificada no existe');
            }
        });
    }
}