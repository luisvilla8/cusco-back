<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    use HasFactory;

    protected $table = "transaction_types";

    protected $fillable = [
        "nombre",
    ];
    
    public static function getTransactionTypeByAgentType(int $agentType)
    {
        return TransactionType::where('id', $agentType)->first();
    }

}
