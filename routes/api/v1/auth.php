<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Rutas públicas de autenticación
Route::name('auth.')->group(function () {
  Route::post('login', [AuthController::class, 'login'])->name('login');
  // Route::post('register', [AuthController::class, 'register'])->name('register');
});
