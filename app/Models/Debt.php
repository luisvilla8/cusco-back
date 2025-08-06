<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Debt
 *
 * @property-read \App\Models\Agent|null $agent
 * @method static \Illuminate\Database\Eloquent\Builder|Debt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Debt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Debt query()
 * @mixin \Eloquent
 */
class Debt extends Model
{
    use HasFactory;

    protected $table = "debts";

    protected $fillable = [
        "agent_id",
        "amount",
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
