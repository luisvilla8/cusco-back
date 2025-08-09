<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Models\Egress;
use DB;

/**
 * App\Models\Trip
 *
 * @property int $id
 * @property int $agent_id
 * @property int $zone_id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string $travel_expenses
 * @property string $code
 * @property \Illuminate\Support\Carbon $date_start
 * @property \Illuminate\Support\Carbon $date_end
 * @property string $total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Agent $agent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Egress> $egresses
 * @property-read int $egresses_count
 * @property-read string $agent_name
 * @property-read string $date_range_text
 * @property-read int $days_since_end
 * @property-read int $days_until_start
 * @property-read string $display_name
 * @property-read int $duration_days
 * @property-read string $formatted_date_end
 * @property-read string $formatted_date_start
 * @property-read string $formatted_duration
 * @property-read string $formatted_net_profit
 * @property-read string $formatted_total
 * @property-read string $formatted_travel_expenses
 * @property-read bool $is_completed
 * @property-read bool $is_in_progress
 * @property-read bool $is_upcoming
 * @property-read float $net_profit
 * @property-read float $profit_margin
 * @property-read float $total_egresses
 * @property-read float $total_revenue
 * @property-read int|null $transactions_count
 * @property-read string $trip_status
 * @property-read string $trip_status_text
 * @property-read array $trip_summary
 * @property-read string $user_email
 * @property-read string $user_name
 * @property-read string $zone_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Zone $zone
 * @method static Builder|Trip active()
 * @method static Builder|Trip byAgent(int $agentId)
 * @method static Builder|Trip byCode(string $code)
 * @method static Builder|Trip byDateStart(string $date)
 * @method static Builder|Trip byUser(int $userId)
 * @method static Builder|Trip byZone(int $zoneId)
 * @method static Builder|Trip completed()
 * @method static Builder|Trip inProgress()
 * @method static Builder|Trip newModelQuery()
 * @method static Builder|Trip newQuery()
 * @method static Builder|Trip onlyTrashed()
 * @method static Builder|Trip orderByDateStart(string $direction = 'desc')
 * @method static Builder|Trip orderByDuration(string $direction = 'desc')
 * @method static Builder|Trip orderByTotal(string $direction = 'desc')
 * @method static Builder|Trip ownTrips()
 * @method static Builder|Trip query()
 * @method static Builder|Trip search(string $search)
 * @method static Builder|Trip upcoming()
 * @method static Builder|Trip whereAgentId($value)
 * @method static Builder|Trip whereCode($value)
 * @method static Builder|Trip whereCreatedAt($value)
 * @method static Builder|Trip whereDateEnd($value)
 * @method static Builder|Trip whereDateStart($value)
 * @method static Builder|Trip whereDeletedAt($value)
 * @method static Builder|Trip whereDescription($value)
 * @method static Builder|Trip whereId($value)
 * @method static Builder|Trip whereName($value)
 * @method static Builder|Trip whereTotal($value)
 * @method static Builder|Trip whereTravelExpenses($value)
 * @method static Builder|Trip whereUpdatedAt($value)
 * @method static Builder|Trip whereUserId($value)
 * @method static Builder|Trip whereZoneId($value)
 * @method static Builder|Trip withEgressesCount()
 * @method static Builder|Trip withFullStats()
 * @method static Builder|Trip withRelations()
 * @method static Builder|Trip withTransactionsCount()
 * @method static Builder|Trip withTrashed()
 * @method static Builder|Trip withoutTrashed()
 * @mixin \Eloquent
 */
class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'trips';

    protected $fillable = [
        'agent_id',
        'zone_id',
        'user_id',        //  NUEVO CAMPO
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

    //  RELACIONES
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     *  NUEVA RELACIÓN: Usuario que creó/maneja el trip
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function egresses(): HasMany
    {
        return $this->hasMany(Egress::class);
    }

    //  SCOPES ACTUALIZADOS
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

    /**
     *  NUEVO SCOPE: Filtrar por usuario
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     *  NUEVO SCOPE: Trips del usuario autenticado
     */
    public function scopeOwnTrips(Builder $query): Builder
    {
        return $query->where('user_id', auth()->id());
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
              })
              ->orWhereHas('user', function ($userQuery) use ($search) {
                  $userQuery->where('name', 'LIKE', "%{$search}%")
                           ->orWhere('email', 'LIKE', "%{$search}%")
                           ->orWhere('code', 'LIKE', "%{$search}%");
              });
        });
    }

    //  SCOPES CON RELACIONES ACTUALIZADOS
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'agent:id,name,code',
            'zone:id,name,code',
            'user:id,name,code,email'  //  AGREGAR user
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

    //  ACCESSORS ACTUALIZADOS
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

    /**
     *  NUEVO ACCESSOR: Nombre del usuario
     */
    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?? 'Sin usuario';
    }

    /**
     *  NUEVO ACCESSOR: Email del usuario
     */
    public function getUserEmailAttribute(): string
    {
        return $this->user?->email ?? '';
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

    //  MÉTODOS DE NEGOCIO ACTUALIZADOS
    public function isActive(): bool
    {
        return is_null($this->deleted_at);
    }

    /**
     *  VERIFICAR SI EL USUARIO PUEDE ACCEDER AL TRIP - SIN LOAD()
     */
    public function canUserAccess(User $user): bool
    {
        // El creador siempre puede acceder
        if ($this->user_id === $user->id) {
            return true;
        }

        // Administradores pueden acceder a todos (usando relación directa)
        if ($user->role && in_array($user->role->name, ['Administrador', 'Super Admin'])) {
            return true;
        }

        // Verificar si el usuario tiene acceso a la zona del trip
        return $user->hasZone($this->zone_id);
    }

    /**
     *  VERIFICAR SI EL USUARIO PUEDE EDITAR EL TRIP - SIN LOAD()
     */
    public function canUserEdit(User $user): bool
    {
        // Solo el creador o administradores pueden editar
        if ($this->user_id === $user->id) {
            return true;
        }

        return $user->role && in_array($user->role->name, ['Administrador', 'Super Admin']);
    }

    //  MÉTODOS ESTÁTICOS ACTUALIZADOS
    public static function createTrip(array $data): self
    {
        // Validar datos requeridos
        $required = ['agent_id', 'zone_id', 'user_id', 'name', 'date_start', 'date_end'];
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

    /**
     *  NUEVO: Obtener trips por usuario con filtros
     */
    public static function getUserTrips(int $userId, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = static::active()->byUser($userId);

        if (!empty($filters['zone_id'])) {
            $query->byZone($filters['zone_id']);
        }

        if (!empty($filters['agent_id'])) {
            $query->byAgent($filters['agent_id']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['date_start'])) {
            $query->byDateStart($filters['date_start']);
        }

        if (!empty($filters['status'])) {
            match($filters['status']) {
                'upcoming' => $query->upcoming(),
                'in_progress' => $query->inProgress(),
                'completed' => $query->completed(),
                default => null
            };
        }

        return $query->withRelations()->orderByDateStart();
    }

    //  VALIDACIONES EN EVENTOS DEL MODELO ACTUALIZADOS
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($trip) {
            if (empty($trip->code)) {
                $trip->code = 'TEMP-' . uniqid() . '-' . now()->format('His');
            }
            
            //  NO AUTO-ASIGNAR USUARIO AQUÍ - SE HACE EN EL SERVICE
            // La lógica de asignación se maneja en TripService para mayor control
            
            // Establecer valores por defecto
            if (is_null($trip->travel_expenses)) {
                $trip->travel_expenses = 0;
            }
            
            if (is_null($trip->total)) {
                $trip->total = 0;
            }
            
            // Normalizar datos
            $trip->name = ucwords(trim($trip->name));
            
            //  VALIDAR QUE SE HAYA ASIGNADO UN USUARIO
            if (empty($trip->user_id)) {
                throw new \InvalidArgumentException('El usuario asignado es obligatorio');
            }
        });

        //  CREAR EGRESO SOLO UNA VEZ - DESPUÉS DE CREAR EL TRIP
        static::created(function ($trip) {
            // Actualizar código si es temporal
            if (strpos($trip->code, 'TEMP-') === 0) {
                //  CARGAR RELACIONES ANTES DE GENERAR CÓDIGO
                $trip->load(['agent', 'zone', 'user']);
                
                //  ACTUALIZAR SIN TRIGGEAR EVENTOS (para evitar loop)
                $trip->updateQuietly([
                    'code' => $trip->generateCode()
                ]);
            }

            //  CREAR EGRESO AUTOMÁTICAMENTE SI HAY travel_expenses - SOLO AQUÍ
            if ($trip->travel_expenses > 0) {
                $trip->createTravelExpenseEgress();
            }

            // Logging
            Log::info("Trip created", [
                'id' => $trip->id,
                'code' => $trip->code,
                'name' => $trip->name,
                'agent_id' => $trip->agent_id,
                'zone_id' => $trip->zone_id,
                'travel_expenses' => $trip->travel_expenses,
                'has_egress' => $trip->travel_expenses > 0
            ]);
        });

        //  ACTUALIZAR EGRESO CUANDO SE ACTUALIZA EL TRIP
        static::updated(function ($trip) {
            // Si cambió travel_expenses, actualizar o crear egreso
            if ($trip->wasChanged('travel_expenses')) {
                $trip->syncTravelExpenseEgress();
            }

            // Si cambió el nombre, actualizar el egreso
            if ($trip->wasChanged('name')) {
                $trip->updateTravelExpenseEgressName();
            }
        });

        //  ELIMINAR EGRESO CUANDO SE ELIMINA EL TRIP (SOFT DELETE) - CORREGIDO
        static::deleting(function ($trip) {
            //  PARA FORCE DELETE, PERMITIR ELIMINACIÓN SIEMPRE
            if ($trip->isForceDeleting()) {
                \Log::warning("Force deleting trip - bypassing dependency checks", [
                    'trip_id' => $trip->id,
                    'dependencies' => $trip->getDependenciesDetails()
                ]);
                return; // No bloquear force delete
            }

            //  PARA SOFT DELETE, VERIFICAR DEPENDENCIAS
            if (!$trip->canBeDeleted()) {
                $dependencies = $trip->getDependenciesDetails();
                
                \Log::error("Cannot soft delete trip due to dependencies", [
                    'trip_id' => $trip->id,
                    'dependencies' => $dependencies
                ]);
                
                $message = 'No se puede eliminar un viaje que tiene ';
                $reasons = [];
                
                if ($dependencies['totals']['transactions_count'] > 0) {
                    $reasons[] = "transacciones asociadas ({$dependencies['totals']['transactions_count']})";
                }
                
                if ($dependencies['totals']['other_egresses_count'] > 0) {
                    $reasons[] = "egresos adicionales ({$dependencies['totals']['other_egresses_count']})";
                }
                
                $message .= implode(' y ', $reasons);
                
                throw new \InvalidArgumentException($message);
            }

            // Si es soft delete, también hacer soft delete del egreso de viaje
            $trip->softDeleteTravelExpenseEgress();
        });

        //  ELIMINAR EGRESO PERMANENTEMENTE CUANDO SE HACE FORCE DELETE
        static::deleted(function ($trip) {
            // Si fue force delete, eliminar permanentemente el egreso
            if ($trip->isForceDeleting()) {
                $trip->forceDeleteTravelExpenseEgress();
            }
        });

        //  VALIDACIÓN ANTES DE GUARDAR - SIN CREAR EGRESOS AQUÍ
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

            //  VALIDAR QUE LOS MODELOS EXISTEN (con imports correctos)
            if (!Agent::find($trip->agent_id)) {
                throw new \InvalidArgumentException('El agente especificado no existe');
            }

            if (!Zone::find($trip->zone_id)) {
                throw new \InvalidArgumentException('La zona especificada no existe');
            }

            if (!User::find($trip->user_id)) {
                throw new \InvalidArgumentException('El usuario especificado no existe');
            }

            // Validar que el usuario tenga acceso a la zona
            $user = User::find($trip->user_id);
            if ($user && !$user->hasAnyRole(['Administrador', 'Super Admin']) && !$user->hasZone($trip->zone_id)) {
                throw new \InvalidArgumentException('El usuario no tiene acceso a la zona especificada');
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
    }

    //  AGREGAR MÉTODOS FALTANTES QUE SE USAN EN LOS SCOPES Y ACCESSORS

    /**
     *  VALIDAR RANGO DE FECHAS
     */
    public function isValidDateRange(): bool
    {
        return $this->date_end >= $this->date_start;
    }

    /**
     *  SCOPES FALTANTES PARA FILTROS DE ESTADO
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('date_start', '>', $today);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('date_start', '<=', $today)
                    ->where('date_end', '>=', $today);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where('date_end', '<', $today);
    }

    /**
     *  SCOPE FALTANTE PARA FILTRO POR FECHA DE INICIO
     */
    public function scopeByDateStart(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_start', $date);
    }

    //  NUEVO MÉTODO: Crear egreso por gastos de viaje
    public function createTravelExpenseEgress(): ?Egress
    {
        if ($this->travel_expenses <= 0) {
            return null;
        }

        return Egress::create([
            'code' => $this->generateEgressCode(),
            'name' => "Gastos de viaje - {$this->name}",
            'description' => "Gastos de viaje para: {$this->name} ({$this->code})\nFecha: {$this->date_range_text}",
            'amount' => $this->travel_expenses,
            'date' => $this->date_start,
            'agent_id' => $this->agent_id,
            'trip_id' => $this->id,
            'zone_id' => $this->zone_id,
        ]);
    }

    //  NUEVO MÉTODO: Sincronizar egreso con travel_expenses - MEJORADO
    public function syncTravelExpenseEgress(): void
    {
        $egress = $this->getTravelExpenseEgress();

        if ($this->travel_expenses <= 0) {
            // Si travel_expenses es 0, eliminar el egreso si existe
            if ($egress) {
                $egress->delete();
                Log::info("Travel expense egress deleted due to zero expenses", [
                    'trip_id' => $this->id,
                    'egress_id' => $egress->id
                ]);
            }
            return;
        }

        if ($egress) {
            // Actualizar egreso existente
            $egress->update([
                'amount' => $this->travel_expenses,
                'date' => $this->date_start,
            ]);
            
            Log::info("Travel expense egress updated", [
                'trip_id' => $this->id,
                'egress_id' => $egress->id,
                'new_amount' => $this->travel_expenses
            ]);
        } else {
            //  CREAR NUEVO EGRESO SOLO SI NO EXISTE
            $newEgress = $this->createTravelExpenseEgress();
            
            Log::info("Travel expense egress created during sync", [
                'trip_id' => $this->id,
                'egress_id' => $newEgress->id,
                'amount' => $this->travel_expenses
            ]);
        }
    }

    //  NUEVO MÉTODO: Actualizar nombre del egreso
    public function updateTravelExpenseEgressName(): void
    {
        $egress = $this->getTravelExpenseEgress();
        
        if ($egress) {
            $egress->update([
                'name' => "Gastos de viaje - {$this->name}",
                'description' => "Gastos de viaje para: {$this->name} ({$this->code})\nFecha: {$this->date_range_text}",
            ]);
        }
    }

    //  NUEVO MÉTODO: Obtener egreso de gastos de viaje
    public function getTravelExpenseEgress(): ?Egress
    {
        return $this->egresses()
            ->where('name', 'LIKE', 'Gastos de viaje - %')
            ->first();
    }

    //  NUEVO MÉTODO: Soft delete del egreso
    public function softDeleteTravelExpenseEgress(): void
    {
        $egress = $this->getTravelExpenseEgress();
        
        if ($egress) {
            $egress->delete(); // Soft delete
            Log::info("Travel expense egress soft deleted", [
                'trip_id' => $this->id,
                'egress_id' => $egress->id
            ]);
        }
    }

    //  NUEVO MÉTODO: Force delete del egress
    public function forceDeleteTravelExpenseEgress(): void
    {
        $egress = Egress::withTrashed()
            ->where('trip_id', $this->id)
            ->where('name', 'LIKE', 'Gastos de viaje - %')
            ->first();
        
        if ($egress) {
            $egress->forceDelete(); // Permanent delete
            Log::warning("Travel expense egress force deleted", [
                'trip_id' => $this->id,
                'egress_id' => $egress->id
            ]);
        }
    }

    //  VERSIÓN CON SECUENCIAL ÚNICO
    public function generateEgressCode(): string
    {
        if (strpos($this->code, 'TEMP-') === 0) {
            return "EGR-TEMP-" . uniqid();
        }
        
        $dateCode = $this->date_start->format('dmy'); // 100825
        
        // Obtener siguiente número secuencial para este trip específico
        $sequential = $this->getNextEgressSequential();
        
        return "EGR-TRP{$this->id}-{$dateCode}-{$sequential}";
    }

    //  MÉTODO AUXILIAR: Obtener siguiente secuencial para egresos de este trip
    private function getNextEgressSequential(): string
    {
        $lastEgress = Egress::where('trip_id', $this->id)
            ->where('code', 'LIKE', "EGR-TRP{$this->id}-%")
            ->whereNull('deleted_at')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastEgress) {
            return '01';
        }
        
        // Extraer número del final del código
        preg_match('/-(\d+)$/', $lastEgress->code, $matches);
        $lastNumber = $matches[1] ?? 0;
        
        return str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);
    }

    //  NUEVO MÉTODO: Verificar si puede ser eliminado (actualizado)
    public function canBeDeleted(): bool
    {
        // Contar transacciones activas
        $activeTransactions = $this->transactions()->count();
        
        // Contar egresos activos (excluyendo el de gastos de viaje)
        $activeEgresses = $this->egresses()
            ->where('name', 'NOT LIKE', 'Gastos de viaje - %')
            ->count();
        
        //  LOG PARA DIAGNÓSTICO
        \Log::info("Checking if trip can be deleted", [
            'trip_id' => $this->id,
            'active_transactions' => $activeTransactions,
            'active_egresses' => $activeEgresses,
            'total_egresses' => $this->egresses()->count(),
            'can_delete' => $activeTransactions === 0 && $activeEgresses === 0
        ]);
        
        return $activeTransactions === 0 && $activeEgresses === 0;
    }

    //  CORREGIR MÉTODO: Obtener detalles de dependencias - CON MEJOR MANEJO DE ERRORES
    public function getDependenciesDetails(): array
    {
        try {
            $transactions = $this->transactions()->select('id', 'code', 'total')->get();
            $egresses = $this->egresses()->select('id', 'code', 'name', 'amount')->get();
            $travelEgresses = $this->egresses()
                ->where('name', 'LIKE', 'Gastos de viaje - %')
                ->select('id', 'code', 'name', 'amount')
                ->get();
            $otherEgresses = $this->egresses()
                ->where('name', 'NOT LIKE', 'Gastos de viaje - %')
                ->select('id', 'code', 'name', 'amount')
                ->get();

            return [
                'transactions' => $transactions->toArray(),
                'all_egresses' => $egresses->toArray(),
                'travel_egresses' => $travelEgresses->toArray(),
                'other_egresses' => $otherEgresses->toArray(),
                'totals' => [
                    'transactions_count' => $transactions->count(),
                    'all_egresses_count' => $egresses->count(),
                    'travel_egresses_count' => $travelEgresses->count(),
                    'other_egresses_count' => $otherEgresses->count(),
                ]
            ];
        } catch (\Exception $e) {
            \Log::error("Error getting dependencies details", [
                'trip_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'transactions' => [],
                'all_egresses' => [],
                'travel_egresses' => [],
                'other_egresses' => [],
                'totals' => [
                    'transactions_count' => 0,
                    'all_egresses_count' => 0,
                    'travel_egresses_count' => 0,
                    'other_egresses_count' => 0,
                ],
                'error' => $e->getMessage()
            ];
        }
    }

    //  MÉTODO ESTÁTICO: Generar código único global
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'TRP-' . now()->format('dmy') . '-' . strtoupper(substr(uniqid(), -4));
        } while (static::where('code', $code)->exists());
        
        return $code;
    }

    //  MÉTODO TEMPORAL: Force delete sin validaciones
    public function forceDeleteWithoutValidations(): bool
    {
        return DB::transaction(function () {
            Log::warning("Force deleting trip without validations", [
                'trip_id' => $this->id
            ]);
            
            try {
                // 1. Eliminar todas las transacciones relacionadas
                $this->transactions()->withTrashed()->forceDelete();
                \Log::info("Transactions force deleted", ['trip_id' => $this->id]);
                
                // 2. Eliminar todos los egresos relacionados
                $this->egresses()->withTrashed()->forceDelete();
                \Log::info("Egresses force deleted", ['trip_id' => $this->id]);
                
                // 3. Eliminar el trip usando método nativo (sin eventos)
                return DB::table('trips')->where('id', $this->id)->delete() > 0;
                
            } catch (\Exception $e) {
                \Log::error("Error in force delete without validations", [
                    'trip_id' => $this->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    //  AGREGAR MÉTODO FALTANTE: generateCode()
    public function generateCode(): string
    {
        $year = $this->date_start->format('y'); // 25 para 2025
        $month = $this->date_start->format('m'); // 08 para agosto  
        $day = $this->date_start->format('d'); // 10 para día 10
        
        // Obtener siguiente número secuencial del día
        $sequential = static::where('code', 'LIKE', "TRP-{$year}{$month}{$day}-%")
            ->where('code', 'NOT LIKE', 'TEMP-%')
            ->count() + 1;
        
        return "TRP-{$year}{$month}{$day}-" . str_pad($sequential, 3, '0', STR_PAD_LEFT);
    }

    //  MÉTODO AUXILIAR: Obtener siguiente secuencial para una fecha
    private function getNextSequentialForDate(string $date): string
    {
        $lastTrip = static::where('date_start', $date)
            ->where('code', 'NOT LIKE', 'TEMP-%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastTrip) {
            return '01';
        }
        
        // Extraer número secuencial del código (último segmento después del último guión)
        $codeParts = explode('-', $lastTrip->code);
        $lastSequential = (int) end($codeParts);
        
        return str_pad($lastSequential + 1, 2, '0', STR_PAD_LEFT);
    }
}