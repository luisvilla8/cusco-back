<?php
// filepath: c:\Users\Martin\Documents\proyecto-luis-cusco\cusco-back\routes\api.php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    
    // Rutas públicas (sin autenticación)
    require __DIR__ . '/api/v1/public.php';
    
    // Rutas protegidas (con autenticación)
    Route::middleware('api.auth')->group(function () {
        require __DIR__ . '/api/v1/protected.php';
    });
    
});