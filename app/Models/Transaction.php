<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'agent_id',
        'user_id',
        'description',
        'code',
        'zone_id',
        'transaction_type_id',
        'amount_paid',
        'date',
        'total',
        'trip_id',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'total' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function transactionPayments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
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

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByTransactionType(Builder $query, int $transactionTypeId): Builder
    {
        return $query->where('transaction_type_id', $transactionTypeId);
    }

    public function scopeByTrip(Builder $query, int $tripId): Builder
    {
        return $query->where('trip_id', $tripId);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhereHas('agent', function ($agentQuery) use ($search) {
                  $agentQuery->where('name', 'LIKE', "%{$search}%")
                             ->orWhere('code', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('user', function ($userQuery) use ($search) {
                  $userQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
              });
        });
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
            $query->where('total', '>=', $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('total', '<=', $maxAmount);
        }
        return $query;
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'agent:id,name,code',
            'user:id,name,code',
            'zone:id,name,code',
            'transactionType:id,name,code',
            'trip:id,name,code'
        ]);
    }

    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with([
            'transactionDetails.product:id,name,code',
            'transactionPayments.paymentMethod:id,name,code'
        ]);
    }

    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('date', $direction);
    }

    public function scopeOrderByAmount(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('total', $direction);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', now()->toDateString());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString()
        ]);
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

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->whereHas('transactionType', function ($typeQuery) {
            $typeQuery->where('code', 'SALE');
        });
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query->whereHas('transactionType', function ($typeQuery) {
            $typeQuery->where('code', 'PURCHASE');
        });
    }

    public function scopePaidTransactions(Builder $query): Builder
    {
        return $query->whereColumn('amount_paid', '>=', 'total');
    }

    public function scopeUnpaidTransactions(Builder $query): Builder
    {
        return $query->whereColumn('amount_paid', '<', 'total');
    }

    public function scopePartiallyPaidTransactions(Builder $query): Builder
    {
        return $query->where('amount_paid', '>', 0)
                    ->whereColumn('amount_paid', '<', 'total');
    }

    // ✅ ACCESSORS
    public function getFormattedTotalAttribute(): string
    {
        return "S/ " . number_format($this->total, 2);
    }

    public function getFormattedAmountPaidAttribute(): string
    {
        return "S/ " . number_format($this->amount_paid, 2);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('d/m/Y');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->transactionType?->name} - {$this->code}";
    }

    public function getAgentNameAttribute(): string
    {
        return $this->agent?->name ?? 'Sin agente';
    }

    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?? 'Sin usuario';
    }

    public function getZoneNameAttribute(): string
    {
        return $this->zone?->name ?? 'Sin zona';
    }

    public function getTransactionTypeNameAttribute(): string
    {
        return $this->transactionType?->name ?? 'Tipo no definido';
    }

    public function getTripNameAttribute(): string
    {
        return $this->trip?->name ?? 'Sin viaje';
    }

    public function getPendingAmountAttribute(): float
    {
        return round($this->total - $this->amount_paid, 2);
    }

    public function getFormattedPendingAmountAttribute(): string
    {
        return "S/ " . number_format($this->pending_amount, 2);
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->amount_paid <= 0) return 'unpaid';
        if ($this->amount_paid >= $this->total) return 'paid';
        return 'partial';
    }

    public function getPaymentStatusTextAttribute(): string
    {
        return match($this->payment_status) {
            'paid' => 'Pagado',
            'partial' => 'Pago parcial',
            'unpaid' => 'Sin pagar',
            default => 'Desconocido'
        };
    }

    public function getPaymentPercentageAttribute(): float
    {
        if ($this->total <= 0) return 0;
        return round(($this->amount_paid / $this->total) * 100, 2);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->amount_paid >= $this->total;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->amount_paid < $this->total;
    }

    public function getIsUnpaidAttribute(): bool
    {
        return $this->amount_paid <= 0;
    }

    public function getDetailsCountAttribute(): int
    {
        return $this->transactionDetails()->count();
    }

    public function getPaymentsCountAttribute(): int
    {
        return $this->transactionPayments()->count();
    }

    public function getContextInfoAttribute(): string
    {
        $parts = [];
        
        if ($this->agent) $parts[] = "Agente: {$this->agent->name}";
        if ($this->user) $parts[] = "Usuario: {$this->user->name}";
        if ($this->zone) $parts[] = "Zona: {$this->zone->name}";
        if ($this->trip) $parts[] = "Viaje: {$this->trip->name}";
        
        return implode(' | ', $parts) ?: 'Sin contexto';
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        // Verificar si tiene detalles o pagos
        return $this->transactionDetails()->count() === 0 && 
               $this->transactionPayments()->count() === 0;
    }

    public function generateCode(): string
    {
        // Prefijo basado en el tipo de transacción
        $typeCode = $this->transactionType?->code ?? 'TXN';
        
        // Fecha en formato YYMMDD
        $dateCode = ($this->date ?? now())->format('ymd');
        
        // Número secuencial para el día y tipo
        $dailyCount = static::whereHas('transactionType', function ($query) use ($typeCode) {
            $query->where('code', $typeCode);
        })
        ->whereDate('date', $this->date ?? now()->toDateString())
        ->whereNull('deleted_at')
        ->count() + 1;
        
        $sequentialNumber = str_pad($dailyCount, 4, '0', STR_PAD_LEFT);
        
        return "{$typeCode}-{$dateCode}-{$sequentialNumber}";
    }

    public function isSale(): bool
    {
        return $this->transactionType?->code === 'SALE';
    }

    public function isPurchase(): bool
    {
        return $this->transactionType?->code === 'PURCHASE';
    }

    public function isRelatedToTrip(): bool
    {
        return !is_null($this->trip_id);
    }

    public function hasDetails(): bool
    {
        return $this->transactionDetails()->exists();
    }

    public function hasPayments(): bool
    {
        return $this->transactionPayments()->exists();
    }

    public function addPayment(int $paymentMethodId, float $amount): TransactionPayment
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del pago debe ser mayor a 0');
        }

        if ($this->amount_paid + $amount > $this->total) {
            throw new \InvalidArgumentException('El monto del pago excede el saldo pendiente');
        }

        $payment = $this->transactionPayments()->create([
            'payment_method_id' => $paymentMethodId,
            'amount_paid' => $amount
        ]);

        // Actualizar amount_paid de la transacción
        $this->increment('amount_paid', $amount);

        return $payment;
    }

    public function calculateTotal(): float
    {
        return $this->transactionDetails()->get()->sum('subtotal');
    }

    public function updateTotalFromDetails(): void
    {
        $this->update(['total' => $this->calculateTotal()]);
    }

    public function updateAmountPaidFromPayments(): void
    {
        $totalPaid = $this->transactionPayments()->sum('amount_paid');
        $this->update(['amount_paid' => $totalPaid]);
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function getTotalByDateRange(string $startDate, string $endDate): float
    {
        return static::active()
            ->dateRange($startDate, $endDate)
            ->sum('total');
    }

    public static function getTotalSalesToday(): float
    {
        return static::active()
            ->sales()
            ->today()
            ->sum('total');
    }

    public static function getTotalPurchasesToday(): float
    {
        return static::active()
            ->purchases()
            ->today()
            ->sum('total');
    }

    public static function getTotalByAgent(int $agentId, string $startDate = null, string $endDate = null): float
    {
        $query = static::active()->byAgent($agentId);
        
        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }
        
        return $query->sum('total');
    }

    public static function createTransaction(array $data): self
    {
        // Validar datos requeridos
        $required = ['transaction_type_id', 'total', 'date'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("El campo {$field} es requerido");
            }
        }

        // Establecer amount_paid por defecto
        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        return static::create($data);
    }

    public static function getTransactionsSummary(string $startDate = null, string $endDate = null): array
    {
        $query = static::active();
        
        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }
        
        $transactions = $query->with('transactionType')->get();
        
        return $transactions->groupBy('transactionType.code')
            ->map(function ($group, $typeCode) {
                return [
                    'type_code' => $typeCode,
                    'type_name' => $group->first()->transactionType->name,
                    'count' => $group->count(),
                    'total_amount' => $group->sum('total'),
                    'total_paid' => $group->sum('amount_paid'),
                    'pending_amount' => $group->sum('pending_amount'),
                    'formatted_total' => "S/ " . number_format($group->sum('total'), 2)
                ];
            })->values()->toArray();
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($transaction) {
            if (empty($transaction->code)) {
                $transaction->code = 'TEMP-' . uniqid() . '-' . now()->format('His');
            }
            
            // Establecer amount_paid por defecto
            if (is_null($transaction->amount_paid)) {
                $transaction->amount_paid = 0;
            }
            
            // Si no se proporciona fecha, usar la fecha actual
            if (empty($transaction->date)) {
                $transaction->date = now()->toDateString();
            }
        });

        // Actualizar código después de crear
        static::created(function ($transaction) {
            if (strpos($transaction->code, 'TEMP-') === 0) {
                $transaction->update([
                    'code' => $transaction->generateCode()
                ]);
            }
        });

        // Validación antes de guardar
        static::saving(function ($transaction) {
            // Validar que el total sea positivo
            if ($transaction->total < 0) {
                throw new \InvalidArgumentException('El total de la transacción no puede ser negativo');
            }

            // Validar que amount_paid no sea mayor que total
            if ($transaction->amount_paid > $transaction->total) {
                throw new \InvalidArgumentException('El monto pagado no puede ser mayor al total');
            }

            // Validar unicidad de code (excluyendo temporales)
            if (!str_starts_with($transaction->code, 'TEMP-')) {
                $codeExists = static::where('code', $transaction->code)
                    ->when($transaction->exists, function ($query) use ($transaction) {
                        return $query->where('id', '!=', $transaction->id);
                    })
                    ->whereNull('deleted_at')
                    ->exists();

                if ($codeExists) {
                    throw new \InvalidArgumentException("El código '{$transaction->code}' ya está en uso");
                }
            }

            // Validar que las relaciones existan (si se proporcionan)
            if ($transaction->agent_id && !Agent::find($transaction->agent_id)) {
                throw new \InvalidArgumentException('El agente especificado no existe');
            }
            
            if ($transaction->user_id && !User::find($transaction->user_id)) {
                throw new \InvalidArgumentException('El usuario especificado no existe');
            }
            
            if ($transaction->zone_id && !Zone::find($transaction->zone_id)) {
                throw new \InvalidArgumentException('La zona especificada no existe');
            }
            
            if ($transaction->transaction_type_id && !TransactionType::find($transaction->transaction_type_id)) {
                throw new \InvalidArgumentException('El tipo de transacción especificado no existe');
            }
            
            if ($transaction->trip_id && !Trip::find($transaction->trip_id)) {
                throw new \InvalidArgumentException('El viaje especificado no existe');
            }
        });

        // Validación antes de eliminar
        static::deleting(function ($transaction) {
            if (!$transaction->isForceDeleting() && !$transaction->canBeDeleted()) {
                throw new \InvalidArgumentException('No se puede eliminar una transacción que tiene detalles o pagos asociados');
            }
        });

        // Logging cuando se crea una transacción
        static::created(function ($transaction) {
            Log::info("Transaction created", [
                'id' => $transaction->id,
                'code' => $transaction->code,
                'type' => $transaction->transactionType?->code,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid
            ]);
        });
    }
}
