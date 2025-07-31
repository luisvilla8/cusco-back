<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
