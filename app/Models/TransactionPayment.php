<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\TransactionPayment.php

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
        'payment_method_id',
        'amount_paid',
        'code',
        'description' //  NUEVO CAMPO
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    //  RELACIONES
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    //  SCOPES
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

    public function scopeWithDescription(Builder $query): Builder
    {
        return $query->whereNotNull('description')
                    ->where('description', '!=', '');
    }

    //  ACCESSORS
    public function getFormattedAmountAttribute(): string
    {
        return "S/ " . number_format($this->amount_paid, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = "{$this->paymentMethod?->name} - {$this->formatted_amount}";
        
        if ($this->description) {
            $name .= " ({$this->description})";
        }
        
        return $name;
    }

    public function getShortDescriptionAttribute(): string
    {
        if (!$this->description) return '';
        
        return strlen($this->description) > 50 
            ? substr($this->description, 0, 47) . '...'
            : $this->description;
    }

    public function getHasDescriptionAttribute(): bool
    {
        return !empty($this->description);
    }

    //  EVENTOS DEL MODELO
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transactionPayment) {
            if (empty($transactionPayment->code)) {
                $transactionPayment->code = $transactionPayment->generateCode();
            }
        });

        static::saving(function ($transactionPayment) {
            //  VALIDAR QUE amount_paid SEA UN NÚMERO VÁLIDO
            if (!is_numeric($transactionPayment->amount_paid)) {
                throw new \InvalidArgumentException('El monto del pago debe ser un número válido.');
            }
            
            //  VALIDAR QUE NO SEA CERO
            if ($transactionPayment->amount_paid == 0) {
                throw new \InvalidArgumentException('El monto del pago no puede ser cero.');
            }
            
            //  PERMITIR TANTO PAGOS (POSITIVOS) COMO REEMBOLSOS (POSITIVOS TAMBIÉN)
            // Los reembolsos se registran como montos positivos en transaction_payments
            // porque representan dinero que la empresa entrega al cliente
            
            //  VALIDAR MÉTODO DE PAGO ACTIVO
            if ($transactionPayment->payment_method_id) {
                $paymentMethod = PaymentMethod::active()->find($transactionPayment->payment_method_id);
                if (!$paymentMethod) {
                    throw new \InvalidArgumentException('El método de pago seleccionado no está disponible.');
                }
            }
            
            //  LOGGING MEJORADO PARA DEBUG
            $transaction = Transaction::find($transactionPayment->transaction_id);
            $isReturnTransaction = $transaction ? $transaction->isReturn() : false;
            
            Log::info('TransactionPayment validation passed', [
                'payment_id' => $transactionPayment->id ?? 'creating',
                'transaction_id' => $transactionPayment->transaction_id,
                'amount_paid' => $transactionPayment->amount_paid,
                'is_return_transaction' => $isReturnTransaction,
                'payment_type' => $isReturnTransaction ? 'refund' : 'payment',
                'payment_method_id' => $transactionPayment->payment_method_id,
                'description' => $transactionPayment->description
            ]);
        });
    }

    //  MÉTODOS DE NEGOCIO
    public function generateCode(int $attempt = 0): string
    {
        $prefix = 'PAY';
        $date = now()->format('dmy'); // Formato: 060825
        
        //  OBTENER CONTEO DIARIO INCLUYENDO SOFT DELETED PARA EVITAR DUPLICADOS
        $dailyCount = static::withTrashed() //  INCLUIR SOFT DELETED
            ->whereDate('created_at', now())
            ->count() + 1 + $attempt;
        
        $sequentialNumber = str_pad($dailyCount, 4, '0', STR_PAD_LEFT);
        $proposedCode = "{$prefix}-{$date}-{$sequentialNumber}";
        
        //  VERIFICAR QUE EL CÓDIGO NO EXISTA (INCLUYENDO SOFT DELETED)
        $exists = static::withTrashed() //  INCLUIR SOFT DELETED EN VERIFICACIÓN
            ->where('code', $proposedCode)
            ->exists();
        
        //  SI EXISTE, INTENTAR CON EL SIGUIENTE NÚMERO
        if ($exists && $attempt < 100) {
            return $this->generateCode($attempt + 1);
        }
        
        //  SI DESPUÉS DE 100 INTENTOS SIGUE FALLANDO, USAR TIMESTAMP
        if ($attempt >= 100) {
            $timestamp = now()->format('His'); // HHMMSS
            $proposedCode = "{$prefix}-{$date}-{$timestamp}";
            
            //  VERIFICACIÓN FINAL CON TIMESTAMP
            $timestampExists = static::withTrashed()
                ->where('code', $proposedCode)
                ->exists();
                
            if ($timestampExists) {
                //  ÚLTIMO RECURSO: AGREGAR MICROSEGUNDOS
                $microtime = substr(microtime(true) * 1000, -3);
                $proposedCode = "{$prefix}-{$date}-{$timestamp}{$microtime}";
            }
        }
        
        \Log::info('TransactionPayment code generated', [
            'prefix' => $prefix,
            'date' => $date,
            'daily_count' => $dailyCount,
            'attempts' => $attempt,
            'final_code' => $proposedCode,
            'includes_soft_deleted' => true
        ]);
        
        return $proposedCode;
    }

    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    public function belongsToTransaction(int $transactionId): bool
    {
        return $this->transaction_id === $transactionId;
    }

    public function hasValidAmount(): bool
    {
        return $this->amount_paid > 0;
    }

    public function canBeDeleted(): bool
    {
        return true;
    }

    public function setDescription(string $description = null): void
    {
        $this->description = $description ? trim($description) : null;
        $this->save();
    }

    public function hasDescription(): bool
    {
        return !empty($this->description);
    }

    //  MÉTODOS ESTÁTICOS DE UTILIDAD
    public static function getTotalByTransaction(int $transactionId): float
    {
        return static::active()
            ->byTransaction($transactionId)
            ->sum('amount_paid');
    }

    public static function getPaymentsByMethod(int $paymentMethodId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->byPaymentMethod($paymentMethodId)
            ->with(['transaction:id,code,date', 'paymentMethod:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function createPayment(int $transactionId, int $paymentMethodId, float $amountPaid, string $description = null): self
    {
        return static::create([
            'transaction_id' => $transactionId,
            'payment_method_id' => $paymentMethodId,
            'amount_paid' => $amountPaid,
            'description' => $description
        ]);
    }

    public static function getPaymentsWithDescription(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->withDescription()
            ->with(['transaction:id,code', 'paymentMethod:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    //  MÉTODOS PARA ANÁLISIS Y REPORTES
    public function getPaymentSummary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'amount' => $this->amount_paid,
            'formatted_amount' => $this->formatted_amount,
            'payment_method' => $this->paymentMethod?->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'has_description' => $this->has_description,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'transaction_code' => $this->transaction?->code
        ];
    }

    //  AGREGAR MÉTODOS HELPER MEJORADOS
    public function isRefund(): bool
    {
        // Un reembolso es un pago asociado a una transacción de devolución
        return $this->transaction?->isReturn() && $this->amount_paid > 0;
    }

    public function isPayment(): bool
    {
        // Un pago es un monto asociado a una transacción original (no devolución)
        return !$this->transaction?->isReturn() && $this->amount_paid > 0;
    }

    public function getFormattedAmount(): string
    {
        if ($this->isRefund()) {
            return 'Reembolso: S/ ' . number_format($this->amount_paid, 2);
        } else {
            return 'Pago: S/ ' . number_format($this->amount_paid, 2);
        }
    }

    public function getPaymentType(): string
    {
        return $this->isRefund() ? 'refund' : 'payment';
    }
}