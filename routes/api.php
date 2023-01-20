<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\UserController as UserV1;
use App\Http\Controllers\Api\V1\AuthController as AuthV1;
use App\Http\Controllers\Api\V1\ProductoController as ProductV1;
use App\Http\Controllers\Api\V1\AgentController as AgentV1;
use App\Http\Controllers\Api\V1\AgentTypeController as AgentTypeV1;
use App\Http\Controllers\Api\V1\TransactionController as TransactionV1;
use App\Http\Controllers\Api\V1\TipoMedidaController as MeasureTypeV1;

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

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthV1::class, 'register']);
    Route::post('login', [AuthV1::class, 'login']);
    Route::apiResource('users', UserV1::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('products', [ProductV1::class, 'index']);
    Route::get('measure-types', [MeasureTypeV1::class, 'list']);
});

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('logout', [AuthV1::class, 'logout']);
    Route::apiResource('products', ProductV1::class)
        ->only(['store', 'show', 'update', 'destroy']);
    Route::apiResource('agents', AgentV1::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('agents-types', AgentTypeV1::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('transactions', TransactionV1::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});

