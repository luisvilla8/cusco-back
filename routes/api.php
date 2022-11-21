<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\UserController as UserV1;
use App\Http\Controllers\Api\V1\AuthController as AuthV1;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('logout', [AuthV1::class, 'logout']);
});

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthV1::class, 'register']);
    Route::post('login', [AuthV1::class, 'login']);
    Route::apiResource('usuarios', UserV1::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});
