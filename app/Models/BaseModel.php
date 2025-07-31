<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\app\Models\BaseModel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     */
    protected $dates = ['deleted_at'];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Agregar eventos de auditoría si es necesario
        static::creating(function ($model) {
            // Lógica al crear
        });

        static::deleting(function ($model) {
            // Lógica antes de eliminar
        });
    }
}