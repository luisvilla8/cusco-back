<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $table = "agents";

    protected $fillable = [
        "nombre",
        "telefono",
        "direccion",
        "email",
        "dni",
        "ruc",
        "id_tipo_agente"
    ];
}
