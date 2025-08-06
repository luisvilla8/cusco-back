<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\AgentCategory
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Agent> $agents
 * @property-read int|null $agents_count
 * @method static \Illuminate\Database\Eloquent\Builder|AgentCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentCategory query()
 * @mixin \Eloquent
 */
class AgentCategory extends Model
{
    use HasFactory;

    protected $table = 'agent_categories';

    protected $fillable = [
        'name',
        'description',
    ];

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_agent_category', 'agent_category_id', 'agent_id');
    }
}
