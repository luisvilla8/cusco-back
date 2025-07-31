<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trips';

    protected $fillable = [
        'agent_id',
        'zone_id',
        'name',
        'description',
        'travel_expenses',
        'code',
        'date_start',
        'date_end',
        'total',
    ];

    protected $casts = [
        'travel_expenses' => 'decimal:2',
        'total' => 'decimal:2',
        'date_start' => 'date',
        'date_end' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
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

    public function scopeByAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
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
              })
              ->orWhereHas('zone', function ($zoneQuery) use ($search) {
                  $zoneQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
              });
        });
    }

    public function scopeByDateStart(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_start', $date);
    }

    public function scopeByDateEnd(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_end', $date);
    }

    public function scopeDateStartRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date_start', [$startDate, $endDate]);
    }

    public function scopeDateEndRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('date_end', [$startDate, $endDate]);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('date_start', '<=', $today)
                    ->where('date_end', '>=', $today);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('date_end', '<', now()->toDateString());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date_start', '>', now()->toDateString());
    }

    public function scopeFuture(Builder $query): Builder
    {
        return $query->where('date_start', '>', now()->toDateString());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('date_end', '<', now()->toDateString());
    }

    public function scopeByMonth(Builder $query, int $month, int $year = null): Builder
    {
        $year = $year ?? now()->year;
        return $query->where(function ($q) use ($month, $year) {
            $q->whereMonth('date_start', $month)->whereYear('date_start', $year)
              ->orWhere(function ($q2) use ($month, $year) {
                  $q2->whereMonth('date_end', $month)->whereYear('date_end', $year);
              });
        });
    }

    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->where(function ($q) use ($year) {
            $q->whereYear('date_start', $year)
              ->orWhereYear('date_end', $year);
        });
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->byMonth(now()->month, now()->year);
    }

    public function scopeThisYear(Builder $query): Builder
    {
        return $query->byYear(now()->year);
    }

    public function scopeByDuration(Builder $query, int $minDays = null, int $maxDays = null): Builder
    {
        if ($minDays !== null || $maxDays !== null) {
            $query->selectRaw('*, DATEDIFF(date_end, date_start) + 1 as duration_days');
            
            if ($minDays !== null) {
                $query->havingRaw('duration_days >= ?', [$minDays]);
            }
            if ($maxDays !== null) {
                $query->havingRaw('duration_days <= ?', [$maxDays]);
            }
        }
        return $query;
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'agent:id,name,code',
            'zone:id,name,code'
        ]);
    }

    public function scopeWithTransactionsCount(Builder $query): Builder
    {
        return $query->withCount('transactions');
    }

    public function scopeWithEgressesCount(Builder $query): Builder
    {
        return $query->withCount('egresses');
    }

    public function scopeWithFullStats(Builder $query): Builder
    {
        return $query->withCount(['transactions', 'egresses'])
                    ->withSum('transactions', 'total')
                    ->withSum('egresses', 'amount');
    }

    public function scopeOrderByDateStart(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('date_start', $direction);
    }

    public function scopeOrderByTotal(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('total', $direction);
    }

    public function scopeOrderByDuration(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->selectRaw('*, DATEDIFF(date_end, date_start) + 1 as duration_days')
                    ->orderBy('duration_days', $direction);
    }

    // ✅ ACCESSORS
    public function getFormattedTotalAttribute(): string
    {
        return "S/ " . number_format($this->total, 2);
    }

    public function getFormattedTravelExpensesAttribute(): string
    {
        return "S/ " . number_format($this->travel_expenses, 2);
    }

    public function getFormattedDateStartAttribute(): string
    {
        return $this->date_start->format('d/m/Y');
    }

    public function getFormattedDateEndAttribute(): string
    {
        return $this->date_end->format('d/m/Y');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getAgentNameAttribute(): string
    {
        return $this->agent?->name ?? 'Sin agente';
    }

    public function getZoneNameAttribute(): string
    {
        return $this->zone?->name ?? 'Sin zona';
    }

    public function getDurationDaysAttribute(): int
    {
        return $this->date_start->diffInDays($this->date_end) + 1;
    }

    public function getFormattedDurationAttribute(): string
    {
        $days = $this->duration_days;
        return $days === 1 ? "1 día" : "{$days} días";
    }

    public function getTripStatusAttribute(): string
    {
        $today = now()->toDateString();
        
        if ($this->date_start > $today) return 'upcoming';
        if ($this->date_end < $today) return 'completed';
        return 'in_progress';
    }

    public function getTripStatusTextAttribute(): string
    {
        return match($this->trip_status) {
            'upcoming' => 'Próximo',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            default => 'Desconocido'
        };
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->trip_status === 'upcoming';
    }

    public function getIsInProgressAttribute(): bool
    {
        return $this->trip_status === 'in_progress';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->trip_status === 'completed';
    }

    public function getDaysUntilStartAttribute(): int
    {
        if ($this->is_upcoming) {
            return now()->diffInDays($this->date_start);
        }
        return 0;
    }

    public function getDaysSinceEndAttribute(): int
    {
        if ($this->is_completed) {
            return $this->date_end->diffInDays(now());
        }
        return 0;
    }

    public function getDateRangeTextAttribute(): string
    {
        return "{$this->formatted_date_start} - {$this->formatted_date_end}";
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->transactions()->sum('total');
    }

    public function getTotalEgressesAttribute(): float
    {
        return $this->egresses()->sum('amount');
    }

    public function getNetProfitAttribute(): float
    {
        return round($this->total_revenue - $this->travel_expenses - $this->total_egresses, 2);
    }

    public function getFormattedNetProfitAttribute(): string
    {
        return "S/ " . number_format($this->net_profit, 2);
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->total_revenue <= 0) return 0;
        return round(($this->net_profit / $this->total_revenue) * 100, 2);
    }

    public function getTransactionsCountAttribute(): int
    {
        return $this->transactions_count ?? $this->transactions()->count();
    }

    public function getEgressesCountAttribute(): int
    {
        return $this->egresses_count ?? $this->egresses()->count();
    }

    public function getTripSummaryAttribute(): array
    {
        return [
            'status' => $this->trip_status_text,
            'duration' => $this->formatted_duration,
            'date_range' => $this->date_range_text,
            'total_revenue' => $this->total_revenue,
            'travel_expenses' => $this->travel_expenses,
            'total_egresses' => $this->total_egresses,
            'net_profit' => $this->net_profit,
            'profit_margin' => $this->profit_margin,
            'transactions_count' => $this->transactions_count,
            'egresses_count' => $this->egresses_count
        ];
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        return $this->transactions()->count() === 0 && 
               $this->egresses()->count() === 0;
    }

    public function generateCode(): string
    {
        // Prefijo basado en agente y zona
        $agentCode = $this->agent?->code ?? 'AGT';
        $zoneCode = $this->zone?->code ?? 'ZON';
        
        // Fecha de inicio en formato YYMMDD
        $dateCode = $this->date_start->format('ymd');
        
        // Número secuencial para el día y agente
        $dailyCount = static::where('agent_id', $this->agent_id)
            ->whereDate('date_start', $this->date_start)
            ->whereNull('deleted_at')
            ->count() + 1;
        
        $sequentialNumber = str_pad($dailyCount, 2, '0', STR_PAD_LEFT);
        
        return "TRIP-{$agentCode}-{$zoneCode}-{$dateCode}-{$sequentialNumber}";
    }

    public function isValidDateRange(): bool
    {
        return $this->date_start <= $this->date_end;
    }

    public function hasTransactions(): bool
    {
        return $this->transactions()->exists();
    }

    public function hasEgresses(): bool
    {
        return $this->egresses()->exists();
    }

    public function isOverlappingWith(Trip $otherTrip): bool
    {
        return $this->date_start <= $otherTrip->date_end && 
               $this->date_end >= $otherTrip->date_start;
    }

    public function calculateTotalFromTransactions(): float
    {
        return $this->transactions()->sum('total');
    }

    public function updateTotalFromTransactions(): void
    {
        $this->update(['total' => $this->calculateTotalFromTransactions()]);
    }

    public function addTransaction(array $transactionData): Transaction
    {
        $transactionData['trip_id'] = $this->id;
        return Transaction::create($transactionData);
    }

    public function addEgress(array $egressData): Egress
    {
        $egressData['trip_id'] = $this->id;
        return Egress::create($egressData);
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    public static function getTripsInDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date_start', [$startDate, $endDate])
                      ->orWhereBetween('date_end', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('date_start', '<=', $startDate)
                            ->where('date_end', '>=', $endDate);
                      });
            })
            ->withRelations()
            ->orderByDateStart()
            ->get();
    }

    public static function getTotalRevenueByAgent(int $agentId, string $startDate = null, string $endDate = null): float
    {
        $query = static::active()->byAgent($agentId);
        
        if ($startDate && $endDate) {
            $query = static::getTripsInDateRange($startDate, $endDate)
                ->where('agent_id', $agentId);
            return $query->sum('total');
        }
        
        return $query->sum('total');
    }

    public static function getTotalByZone(int $zoneId, string $startDate = null, string $endDate = null): float
    {
        $query = static::active()->byZone($zoneId);
        
        if ($startDate && $endDate) {
            $query = static::getTripsInDateRange($startDate, $endDate)
                ->where('zone_id', $zoneId);
            return $query->sum('total');
        }
        
        return $query->sum('total');
    }

    public static function createTrip(array $data): self
    {
        // Validar datos requeridos
        $required = ['agent_id', 'zone_id', 'name', 'date_start', 'date_end'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("El campo {$field} es requerido");
            }
        }

        // Establecer valores por defecto
        $data['travel_expenses'] = $data['travel_expenses'] ?? 0;
        $data['total'] = $data['total'] ?? 0;

        return static::create($data);
    }

    public static function getUpcomingTrips(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->upcoming()
            ->where('date_start', '<=', now()->addDays($days)->toDateString())
            ->withRelations()
            ->orderByDateStart('asc')
            ->get();
    }

    public static function getCompletedTripsReport(string $startDate = null, string $endDate = null): array
    {
        $query = static::active()->completed();
        
        if ($startDate && $endDate) {
            $query->dateEndRange($startDate, $endDate);
        }
        
        $trips = $query->withFullStats()->get();
        
        return [
            'total_trips' => $trips->count(),
            'total_revenue' => $trips->sum('total'),
            'total_expenses' => $trips->sum('travel_expenses'),
            'total_egresses' => $trips->sum(fn($trip) => $trip->total_egresses),
            'net_profit' => $trips->sum(fn($trip) => $trip->net_profit),
            'average_duration' => round($trips->avg('duration_days'), 1),
            'average_revenue' => round($trips->avg('total'), 2),
            'best_trip' => $trips->sortByDesc('net_profit')->first()?->only(['name', 'code', 'net_profit']),
            'worst_trip' => $trips->sortBy('net_profit')->first()?->only(['name', 'code', 'net_profit'])
        ];
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($trip) {
            if (empty($trip->code)) {
                $trip->code = 'TEMP-' . uniqid() . '-' . now()->format('His');
            }
            
            // Establecer valores por defecto
            if (is_null($trip->travel_expenses)) {
                $trip->travel_expenses = 0;
            }
            
            if (is_null($trip->total)) {
                $trip->total = 0;
            }
            
            // Normalizar datos
            $trip->name = ucwords(trim($trip->name));
        });

        // Actualizar código después de crear
        static::created(function ($trip) {
            if (strpos($trip->code, 'TEMP-') === 0) {
                $trip->update([
                    'code' => $trip->generateCode()
                ]);
            }
        });

        // Validación antes de guardar
        static::saving(function ($trip) {
            // Validar rango de fechas
            if (!$trip->isValidDateRange()) {
                throw new \InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio');
            }

            // Validar que los valores monetarios no sean negativos
            if ($trip->travel_expenses < 0) {
                throw new \InvalidArgumentException('Los gastos de viaje no pueden ser negativos');
            }

            if ($trip->total < 0) {
                throw new \InvalidArgumentException('El total del viaje no puede ser negativo');
            }

            // Validar unicidad de code (excluyendo temporales)
            if (!str_starts_with($trip->code, 'TEMP-')) {
                $codeExists = static::where('code', $trip->code)
                    ->when($trip->exists, function ($query) use ($trip) {
                        return $query->where('id', '!=', $trip->id);
                    })
                    ->whereNull('deleted_at')
                    ->exists();

                if ($codeExists) {
                    throw new \InvalidArgumentException("El código '{$trip->code}' ya está en uso");
                }
            }

            // Validar que el agente existe
            if (!Agent::find($trip->agent_id)) {
                throw new \InvalidArgumentException('El agente especificado no existe');
            }

            // Validar que la zona existe
            if (!Zone::find($trip->zone_id)) {
                throw new \InvalidArgumentException('La zona especificada no existe');
            }

            // Validar que las fechas no sean muy lejanas en el pasado o futuro
            $maxPastDays = 365; // 1 año atrás
            $maxFutureDays = 365; // 1 año adelante
            
            if ($trip->date_start < now()->subDays($maxPastDays)->toDateString()) {
                throw new \InvalidArgumentException('La fecha de inicio no puede ser mayor a 1 año en el pasado');
            }
            
            if ($trip->date_start > now()->addDays($maxFutureDays)->toDateString()) {
                throw new \InvalidArgumentException('La fecha de inicio no puede ser mayor a 1 año en el futuro');
            }
        });

        // Validación antes de eliminar
        static::deleting(function ($trip) {
            if (!$trip->isForceDeleting() && !$trip->canBeDeleted()) {
                throw new \InvalidArgumentException('No se puede eliminar un viaje que tiene transacciones o egresos asociados');
            }
        });

        // Logging cuando se crea un viaje
        static::created(function ($trip) {
            Log::info("Trip created", [
                'id' => $trip->id,
                'code' => $trip->code,
                'name' => $trip->name,
                'agent_id' => $trip->agent_id,
                'zone_id' => $trip->zone_id,
                'date_start' => $trip->date_start,
                'date_end' => $trip->date_end,
                'duration_days' => $trip->duration_days
            ]);
        });
    }
}