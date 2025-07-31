<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class TransactionPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaction_payments';

    protected $fillable = [
        'transaction_id',
        'amount_paid',
        'code',
        'payment_method_id',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // ✅ SCOPES
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeByPaymentMethod(Builder $query, int $paymentMethodId): Builder
    {
        return $query->where('payment_method_id', $paymentMethodId);
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'LIKE', "%{$search}%")
              ->orWhereHas('transaction', function ($transactionQuery) use ($search) {
                  $transactionQuery->where('code', 'LIKE', "%{$search}%");
              })
              ->orWhereHas('paymentMethod', function ($paymentQuery) use ($search) {
                  $paymentQuery->where('name', 'LIKE', "%{$search}%")
                               ->orWhere('code', 'LIKE', "%{$search}%");
              });
        });
    }

    public function scopeAmountRange(Builder $query, float $minAmount = null, float $maxAmount = null): Builder
    {
        if ($minAmount !== null) {
            $query->where('amount_paid', '>=', $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('amount_paid', '<=', $maxAmount);
        }
        return $query;
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'transaction:id,code,date,total',
            'paymentMethod:id,name,code,is_active'
        ]);
    }

    public function scopeOrderByAmount(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('amount_paid', $direction);
    }

    public function scopeByPaymentType(Builder $query, string $paymentType): Builder
    {
        return $query->whereHas('paymentMethod', function ($paymentQuery) use ($paymentType) {
            $paymentQuery->where('code', $paymentType);
        });
    }

    public function scopeCashPayments(Builder $query): Builder
    {
        return $query->byPaymentType('CASH');
    }

    public function scopeCardPayments(Builder $query): Builder
    {
        return $query->byPaymentType('CARD');
    }

    public function scopeTransferPayments(Builder $query): Builder
    {
        return $query->byPaymentType('TRANSFER');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereHas('transaction', function ($transactionQuery) {
            $transactionQuery->whereDate('date', now()->toDateString());
        });
    }

    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereHas('transaction', function ($transactionQuery) use ($startDate, $endDate) {
            $transactionQuery->whereBetween('date', [$startDate, $endDate]);
        });
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereHas('transaction', function ($transactionQuery) {
            $transactionQuery->whereMonth('date', now()->month)
                            ->whereYear('date', now()->year);
        });
    }

    // ✅ ACCESSORS
    public function getFormattedAmountAttribute(): string
    {
        return "S/ " . number_format($this->amount_paid, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->paymentMethod?->name} - {$this->formatted_amount} ({$this->code})";
    }

    public function getPaymentMethodNameAttribute(): string
    {
        return $this->paymentMethod?->name ?? 'Método no encontrado';
    }

    public function getPaymentMethodCodeAttribute(): string
    {
        return $this->paymentMethod?->code ?? '';
    }

    public function getTransactionCodeAttribute(): string
    {
        return $this->transaction?->code ?? '';
    }

    public function getTransactionDateAttribute(): ?string
    {
        return $this->transaction?->date?->format('d/m/Y');
    }

    public function getIsActivePaymentMethodAttribute(): bool
    {
        return $this->paymentMethod?->is_active ?? false;
    }

    public function getIsCashPaymentAttribute(): bool
    {
        return $this->paymentMethod?->code === 'CASH';
    }

    public function getIsCardPaymentAttribute(): bool
    {
        return $this->paymentMethod?->code === 'CARD';
    }

    public function getIsTransferPaymentAttribute(): bool
    {
        return $this->paymentMethod?->code === 'TRANSFER';
    }

    public function getIsDigitalPaymentAttribute(): bool
    {
        return in_array($this->paymentMethod?->code, ['CARD', 'TRANSFER', 'DIGITAL_WALLET']);
    }

    public function getPaymentStatusAttribute(): string
    {
        if (!$this->is_active_payment_method) return 'inactive_method';
        if ($this->amount_paid <= 0) return 'invalid_amount';
        return 'valid';
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function canBeDeleted(): bool
    {
        // TransactionPayment puede eliminarse si la transacción lo permite
        // Podría verificar el estado de la transacción
        return true;
    }

    public function isValidPayment(): bool
    {
        return $this->transaction_id && 
               $this->payment_method_id && 
               $this->amount_paid > 0 &&
               $this->is_active_payment_method;
    }

    public function belongsToTransaction(int $transactionId): bool
    {
        return $this->transaction_id === $transactionId;
    }

    public function usesPaymentMethod(int $paymentMethodId): bool
    {
        return $this->payment_method_id === $paymentMethodId;
    }

    public function generateCode(): string
    {
        // Prefijo basado en el método de pago
        $methodCode = $this->paymentMethod?->code ?? 'PAY';
        $prefix = "PAY-{$methodCode}";
        
        // Fecha en formato YYMMDD
        $dateCode = now()->format('ymd');
        
        // Número secuencial para el día y método de pago
        $dailyCount = static::whereHas('paymentMethod', function ($query) use ($methodCode) {
            $query->where('code', $methodCode);
        })
        ->whereDate('created_at', now()->toDateString())
        ->whereNull('deleted_at')
        ->count() + 1;
        
        $sequentialNumber = str_pad($dailyCount, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$dateCode}-{$sequentialNumber}";
    }

    public function isSamePaymentMethod(string $methodCode): bool
    {
        return $this->paymentMethod?->code === $methodCode;
    }

    public function getPaymentPercentageOfTransaction(): float
    {
        $transactionTotal = $this->transaction?->total ?? 0;
        if ($transactionTotal <= 0) return 0;
        
        return round(($this->amount_paid / $transactionTotal) * 100, 2);
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function getTotalByPaymentMethod(int $paymentMethodId, string $startDate = null, string $endDate = null): float
    {
        $query = static::active()->byPaymentMethod($paymentMethodId);
        
        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }
        
        return $query->sum('amount_paid');
    }

    public static function getTotalByTransaction(int $transactionId): float
    {
        return static::active()
            ->byTransaction($transactionId)
            ->sum('amount_paid');
    }

    public static function getTotalCashToday(): float
    {
        return static::active()
            ->cashPayments()
            ->today()
            ->sum('amount_paid');
    }

    public static function getTotalCardToday(): float
    {
        return static::active()
            ->cardPayments()
            ->today()
            ->sum('amount_paid');
    }

    public static function getTotalThisMonth(): float
    {
        return static::active()
            ->thisMonth()
            ->sum('amount_paid');
    }

    public static function createPayment(int $transactionId, int $paymentMethodId, float $amountPaid): self
    {
        return static::create([
            'transaction_id' => $transactionId,
            'payment_method_id' => $paymentMethodId,
            'amount_paid' => $amountPaid
        ]);
    }

    public static function getPaymentMethodsSummary(string $startDate = null, string $endDate = null): array
    {
        $query = static::active()->withRelations();
        
        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }
        
        return $query->get()
            ->groupBy('payment_method.code')
            ->map(function ($payments, $methodCode) {
                return [
                    'method_code' => $methodCode,
                    'method_name' => $payments->first()->payment_method->name,
                    'total_amount' => $payments->sum('amount_paid'),
                    'count' => $payments->count(),
                    'average_amount' => round($payments->avg('amount_paid'), 2)
                ];
            })->values()->toArray();
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($payment) {
            if (empty($payment->code)) {
                // Código temporal antes de tener ID y relaciones
                $payment->code = 'TEMP-' . uniqid() . '-' . now()->format('His');
            }
        });

        // Actualizar código después de crear (cuando ya tenemos relaciones)
        static::created(function ($payment) {
            if (strpos($payment->code, 'TEMP-') === 0) {
                $payment->update([
                    'code' => $payment->generateCode()
                ]);
            }
        });

        // Validación antes de guardar
        static::saving(function ($payment) {
            // Validar que el monto sea positivo
            if ($payment->amount_paid <= 0) {
                throw new \InvalidArgumentException('El monto pagado debe ser mayor a 0');
            }

            // Validar unicidad de code (excluyendo temporales)
            if (!str_starts_with($payment->code, 'TEMP-')) {
                $codeExists = static::where('code', $payment->code)
                    ->when($payment->exists, function ($query) use ($payment) {
                        return $query->where('id', '!=', $payment->id);
                    })
                    ->whereNull('deleted_at')
                    ->exists();

                if ($codeExists) {
                    throw new \InvalidArgumentException("El código '{$payment->code}' ya está en uso");
                }
            }

            // Validar que la transacción existe
            if (!Transaction::find($payment->transaction_id)) {
                throw new \InvalidArgumentException('La transacción especificada no existe');
            }

            // Validar que el método de pago existe y está activo
            $paymentMethod = PaymentMethod::find($payment->payment_method_id);
            if (!$paymentMethod) {
                throw new \InvalidArgumentException('El método de pago especificado no existe');
            }
            if (!$paymentMethod->is_active) {
                throw new \InvalidArgumentException('El método de pago especificado está inactivo');
            }
        });

        // Logging cuando se crea un pago
        static::created(function ($payment) {
            Log::info("TransactionPayment created", [
                'transaction_id' => $payment->transaction_id,
                'payment_method_id' => $payment->payment_method_id,
                'amount_paid' => $payment->amount_paid,
                'code' => $payment->code
            ]);
        });
    }
}
