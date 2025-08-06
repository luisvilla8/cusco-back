<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\PaymentMethod.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\PaymentMethod
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read float $average_payment_amount
 * @property-read string $display_name
 * @property-read string $formatted_average_payment
 * @property-read string $formatted_total_amount
 * @property-read bool $is_card
 * @property-read bool $is_cash
 * @property-read bool $is_digital
 * @property-read bool $is_transfer
 * @property-read string $payment_type
 * @property-read int $payments_count
 * @property-read float $total_amount_processed
 * @property-read array $usage_stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TransactionPayment> $transactionPayments
 * @property-read int|null $transaction_payments_count
 * @method static Builder|PaymentMethod active()
 * @method static Builder|PaymentMethod byCode(string $code)
 * @method static Builder|PaymentMethod byName(string $name)
 * @method static Builder|PaymentMethod newModelQuery()
 * @method static Builder|PaymentMethod newQuery()
 * @method static Builder|PaymentMethod onlyTrashed()
 * @method static Builder|PaymentMethod orderByUsage(string $direction = 'desc')
 * @method static Builder|PaymentMethod popular(int $limit = 5)
 * @method static Builder|PaymentMethod query()
 * @method static Builder|PaymentMethod search(string $search)
 * @method static Builder|PaymentMethod whereCode($value)
 * @method static Builder|PaymentMethod whereCreatedAt($value)
 * @method static Builder|PaymentMethod whereDeletedAt($value)
 * @method static Builder|PaymentMethod whereDescription($value)
 * @method static Builder|PaymentMethod whereId($value)
 * @method static Builder|PaymentMethod whereName($value)
 * @method static Builder|PaymentMethod whereUpdatedAt($value)
 * @method static Builder|PaymentMethod withPaymentsCount()
 * @method static Builder|PaymentMethod withPaymentsSum()
 * @method static Builder|PaymentMethod withTrashed()
 * @method static Builder|PaymentMethod withoutTrashed()
 * @mixin \Eloquent
 */
class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ✅ RELACIONES
    public function transactionPayments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
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

    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where('name', 'LIKE', "%{$name}%");
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('code', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    public function scopeWithPaymentsCount(Builder $query): Builder
    {
        return $query->withCount('transactionPayments');
    }

    public function scopeOrderByUsage(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->withCount('transactionPayments')
                    ->orderBy('transaction_payments_count', $direction);
    }

    public function scopePopular(Builder $query, int $limit = 5): Builder
    {
        return $query->orderByUsage('desc')->limit($limit);
    }

    public function scopeWithPaymentsSum(Builder $query): Builder
    {
        return $query->withSum('transactionPayments', 'amount_paid');
    }

    // ✅ ACCESSORS
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getPaymentsCountAttribute(): int
    {
        return $this->transaction_payments_count ?? $this->transactionPayments()->count();
    }

    public function getTotalAmountProcessedAttribute(): float
    {
        return $this->transaction_payments_sum_amount_paid ?? 
               $this->transactionPayments()->sum('amount_paid');
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return "S/ " . number_format($this->total_amount_processed, 2);
    }

    public function getAveragePaymentAmountAttribute(): float
    {
        $count = $this->payments_count;
        if ($count <= 0) return 0;
        
        return round($this->total_amount_processed / $count, 2);
    }

    public function getFormattedAveragePaymentAttribute(): string
    {
        return "S/ " . number_format($this->average_payment_amount, 2);
    }

    public function getIsCashAttribute(): bool
    {
        return $this->code === 'CASH';
    }

    public function getIsCardAttribute(): bool
    {
        return $this->code === 'CARD';
    }

    public function getIsTransferAttribute(): bool
    {
        return $this->code === 'TRANSFER';
    }

    public function getIsDigitalAttribute(): bool
    {
        return in_array($this->code, ['CARD', 'TRANSFER', 'DIGITAL_WALLET', 'PAYPAL', 'CRYPTO']);
    }

    public function getPaymentTypeAttribute(): string
    {
        return match($this->code) {
            'CASH' => 'Físico',
            'CARD' => 'Tarjeta',
            'TRANSFER' => 'Transferencia',
            'DIGITAL_WALLET' => 'Billetera Digital',
            'CHECK' => 'Cheque',
            'CRYPTO' => 'Criptomoneda',
            default => 'Otro'
        };
    }

    public function getUsageStatsAttribute(): array
    {
        return [
            'total_payments' => $this->payments_count,
            'total_amount' => $this->total_amount_processed,
            'average_amount' => $this->average_payment_amount,
            'formatted_total' => $this->formatted_total_amount,
            'formatted_average' => $this->formatted_average_payment
        ];
    }

    // ✅ MÉTODOS DE NEGOCIO
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function hasPayments(): bool
    {
        return $this->transactionPayments()->exists();
    }

    public function hasPaymentsInHistory(): bool
    {
        return $this->transactionPayments()->withTrashed()->exists();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasPayments();
    }

    public function generateCode(): string
    {
        // Generar código basado en el nombre
        $baseName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->name));
        
        // Abreviaciones comunes para métodos de pago
        $abbreviations = [
            'EFECTIVO' => 'CASH',
            'TARJETA' => 'CARD',
            'TRANSFERENCIA' => 'TRANSFER',
            'BILLETERA' => 'WALLET',
            'DIGITAL' => 'DIGITAL',
            'PAYPAL' => 'PAYPAL',
            'BITCOIN' => 'BTC',
            'CHEQUE' => 'CHECK',
            'CREDITO' => 'CREDIT',
            'DEBITO' => 'DEBIT'
        ];

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

        $baseName = $baseName ?: 'METHOD';

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

    public function isCodeType(string $codeType): bool
    {
        return $this->code === strtoupper($codeType);
    }

    public function getPaymentsInDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return $this->transactionPayments()
            ->whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->with(['transaction:id,code,date'])
            ->get();
    }

    public function getTotalInDateRange(string $startDate, string $endDate): float
    {
        return $this->transactionPayments()
            ->whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->sum('amount_paid');
    }

    public function getPaymentsToday(): float
    {
        return $this->transactionPayments()
            ->whereHas('transaction', function ($query) {
                $query->whereDate('date', now()->toDateString());
            })
            ->sum('amount_paid');
    }

    public function getPaymentsThisMonth(): float
    {
        return $this->transactionPayments()
            ->whereHas('transaction', function ($query) {
                $query->whereMonth('date', now()->month)
                      ->whereYear('date', now()->year);
            })
            ->sum('amount_paid');
    }

    // ✅ MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    public static function getPopularMethods(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->popular($limit)
            ->withPaymentsCount()
            ->withPaymentsSum()
            ->get();
    }

    public static function getMethodsWithStats(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->withPaymentsCount()
            ->withPaymentsSum()
            ->orderBy('name')
            ->get();
    }

    public static function createStandardMethods(): array
    {
        $standardMethods = [
            ['name' => 'Efectivo', 'description' => 'Pago en efectivo'],
            ['name' => 'Tarjeta de Crédito', 'description' => 'Pago con tarjeta de crédito'],
            ['name' => 'Tarjeta de Débito', 'description' => 'Pago con tarjeta de débito'],
            ['name' => 'Transferencia Bancaria', 'description' => 'Transferencia electrónica'],
            ['name' => 'Billetera Digital', 'description' => 'Pago con billetera electrónica'],
        ];

        $created = [];
        foreach ($standardMethods as $method) {
            $paymentMethod = static::create($method);
            $created[] = $paymentMethod;
        }

        return $created;
    }

    public static function getPaymentsSummaryByMethod(string $startDate = null, string $endDate = null): array
    {
        $query = static::active()->withPaymentsCount();

        $methods = $query->get();

        return $methods->map(function ($method) use ($startDate, $endDate) {
            $total = $startDate && $endDate 
                ? $method->getTotalInDateRange($startDate, $endDate)
                : $method->total_amount_processed;

            return [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'payment_type' => $method->payment_type,
                'total_amount' => $total,
                'formatted_total' => "S/ " . number_format($total, 2),
                'payments_count' => $method->payments_count,
                'is_digital' => $method->is_digital
            ];
        })->sortByDesc('total_amount')->values()->toArray();
    }

    // ✅ VALIDACIONES EN EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($paymentMethod) {
            if (empty($paymentMethod->code)) {
                $paymentMethod->code = $paymentMethod->generateCode();
            }
            
            // Normalizar datos
            $paymentMethod->name = ucwords(trim($paymentMethod->name));
            $paymentMethod->code = strtoupper(trim($paymentMethod->code));
        });

        // Validación antes de guardar
        static::saving(function ($paymentMethod) {
            // Validar unicidad de name
            $nameExists = static::where('name', $paymentMethod->name)
                ->when($paymentMethod->exists, function ($query) use ($paymentMethod) {
                    return $query->where('id', '!=', $paymentMethod->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($nameExists) {
                throw new \InvalidArgumentException("El nombre '{$paymentMethod->name}' ya está en uso");
            }

            // Validar unicidad de code
            $codeExists = static::where('code', $paymentMethod->code)
                ->when($paymentMethod->exists, function ($query) use ($paymentMethod) {
                    return $query->where('id', '!=', $paymentMethod->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$paymentMethod->code}' ya está en uso");
            }
        });

        // Validación antes de eliminar
        static::deleting(function ($paymentMethod) {
            if (!$paymentMethod->isForceDeleting()) {
                \App\Rules\PaymentMethodBusinessRules::validateDeletion($paymentMethod);
            }
        });

        // Validación antes de eliminar físicamente
        static::forceDeleting(function ($paymentMethod) {
            \App\Rules\PaymentMethodBusinessRules::validateForceDeletion($paymentMethod);
        });

        // Logging cuando se crea un método de pago
        static::created(function ($paymentMethod) {
            \Illuminate\Support\Facades\Log::info("PaymentMethod created", [
                'id' => $paymentMethod->id,
                'code' => $paymentMethod->code,
                'name' => $paymentMethod->name
            ]);
        });
    }
}