<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = "usuarios";

    protected $fillable = [
        'nombre',
        'apellidos',
        'email',
        'password',
        'id_tipo_usuario',
        'telefono'
    ];
}
