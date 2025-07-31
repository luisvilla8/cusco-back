<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
