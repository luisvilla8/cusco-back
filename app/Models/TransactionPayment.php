<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionPayment extends Model
{
    use HasFactory;

    protected $table = "transaction_payments";

    protected $fillable = [
        "id_transaccion",
        "monto_pagado"
    ];
}
