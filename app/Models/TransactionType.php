<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\TransactionType
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $display_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType active()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType byCode(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType withoutTrashed()
 * @mixin \Eloquent
 */
class TransactionType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "transaction_types";

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
        static::creating(function ($transactionType) {
            if (empty($transactionType->code)) {
                $transactionType->code = $transactionType->generateCode();
            }
            
            // Normalizar datos
            $transactionType->name = ucwords(trim($transactionType->name));
            $transactionType->code = strtoupper(trim($transactionType->code));
        });

        // Validar unicidad de code
        static::saving(function ($transactionType) {
            $codeExists = static::where('code', $transactionType->code)
                ->when($transactionType->exists, function ($query) use ($transactionType) {
                    return $query->where('id', '!=', $transactionType->id);
                })
                ->whereNull('deleted_at')
                ->exists();

            if ($codeExists) {
                throw new \InvalidArgumentException("El código '{$transactionType->code}' ya está en uso");
            }
        });
    }

    /**
     * Get all transactions of this type
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope para tipos activos
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
     * Generate unique code for transaction type
     */
    public function generateCode(): string
    {
        // Abreviaciones comunes para tipos de transacción
        $abbreviations = [
            'VENTA' => 'SALE',
            'COMPRA' => 'PURCHASE',
            'DEVOLUCION' => 'RETURN',
            'PAGO' => 'PAYMENT',
            'TRANSFERENCIA' => 'TRANSFER',
            'AJUSTE' => 'ADJUST',
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
     * Find transaction type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::active()->byCode($code)->first();
    }

    public static function getTransactionTypeByAgentType(int $agentType)
    {
        return TransactionType::where('id', $agentType)->first();
    }

    //  AGREGAR SCOPE DE BÚSQUEDA
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('code', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    //  AGREGAR ACCESSOR
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
