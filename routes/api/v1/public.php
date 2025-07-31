<?php

use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TipoMedidaController;
use Illuminate\Support\Facades\Route;

Route::name('public.')->group(function () {
    // Autenticación pública
    Route::prefix('auth')->group(function () {
        require __DIR__ . '/auth.php';
    });
    
    // Usuarios públicos (registro)
    Route::post('users', [UserController::class, 'store'])->name('users.store');

    // Productos públicos
    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    // Tipos de medida
    Route::get('measure-types', [TipoMedidaController::class, 'list'])->name('measure-types.index');
});