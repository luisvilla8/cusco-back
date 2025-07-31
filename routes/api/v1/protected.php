<?php

use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AgentCategoryController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\SalesController;
use App\Http\Controllers\Api\V1\PurchasesController;
use App\Http\Controllers\Api\V1\DebtsController;
use App\Http\Controllers\Api\V1\AgentTypeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TransactionDetailController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeasureTypeController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductPriceDetailController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

// AUTENTICACIÓN PROTEGIDA
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('revoke-all', [AuthController::class, 'revokeAllTokens'])->name('revoke-all');
    Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
});

// USUARIOS
Route::prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('{user}', [UserController::class, 'show'])->name('show');
    Route::put('{user}', [UserController::class, 'update'])->name('update');
    Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('{user}/change-password', [UserController::class, 'changePassword'])->name('change-password');
});

// ROLES 
Route::prefix('roles')->name('roles.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [RoleController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::post('/', [RoleController::class, 'store'])->name('store');
    Route::get('{id}', [RoleController::class, 'show'])->name('show');
    Route::put('{id}', [RoleController::class, 'update'])->name('update');
    Route::delete('{id}', [RoleController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [RoleController::class, 'forceDelete'])->name('force-delete'); // ✅ NUEVO: Eliminado físico
});

// UNIDADES DE MEDIDA 
Route::prefix('measure-types')->name('measure-types.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [MeasureTypeController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [MeasureTypeController::class, 'index'])->name('index');
    Route::post('/', [MeasureTypeController::class, 'store'])->name('store');
    Route::get('{id}', [MeasureTypeController::class, 'show'])->name('show');
    Route::put('{id}', [MeasureTypeController::class, 'update'])->name('update');
    Route::delete('{id}', [MeasureTypeController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [MeasureTypeController::class, 'forceDelete'])->name('force-delete'); // ✅ NUEVO: Eliminado físico
});

// CATEGORÍAS DE PRODUCTOS 
Route::prefix('product-categories')->name('product-categories.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [ProductCategoryController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [ProductCategoryController::class, 'index'])->name('index');
    Route::post('/', [ProductCategoryController::class, 'store'])->name('store');
    Route::get('{id}', [ProductCategoryController::class, 'show'])->name('show');
    Route::put('{id}', [ProductCategoryController::class, 'update'])->name('update');
    Route::delete('{id}', [ProductCategoryController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [ProductCategoryController::class, 'forceDelete'])->name('force-delete');
});

// PRODUCTOS
Route::prefix('products')->name('products.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [ProductController::class, 'list'])->name('list'); // Para dropdowns
    Route::get('low-stock', [ProductController::class, 'lowStock'])->name('low-stock'); // Stock bajo
    Route::get('out-of-stock', [ProductController::class, 'outOfStock'])->name('out-of-stock'); // Sin stock
    Route::get('category/{categoryId}', [ProductController::class, 'byCategory'])->name('by-category'); // Por categoría
    
    Route::patch('{id}/stock/add', [ProductController::class, 'addStock'])->name('add-stock'); // Agregar stock
    Route::patch('{id}/stock/remove', [ProductController::class, 'removeStock'])->name('remove-stock'); // Quitar stock
    Route::patch('{id}/stock/set', [ProductController::class, 'setStock'])->name('set-stock'); // Establecer stock

    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('{id}', [ProductController::class, 'show'])->name('show');
    Route::put('{id}', [ProductController::class, 'update'])->name('update');                    // Para JSON
    Route::post('{id}', [ProductController::class, 'update'])->name('update-form');             // Para formularios
    Route::delete('{id}', [ProductController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [ProductController::class, 'forceDelete'])->name('force-delete');
});

// ZONAS
Route::prefix('zones')->name('zones.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [ZoneController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [ZoneController::class, 'index'])->name('index');
    Route::post('/', [ZoneController::class, 'store'])->name('store');
    Route::get('{id}', [ZoneController::class, 'show'])->name('show');
    Route::put('{id}', [ZoneController::class, 'update'])->name('update');
    Route::delete('{id}', [ZoneController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [ZoneController::class, 'forceDelete'])->name('force-delete');
});








// TIPOS DE AGENTE (ACTUALIZADO SIGUIENDO PATRÓN DE ROLES)
Route::prefix('agent-types')->name('agent-types.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [AgentTypeController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [AgentTypeController::class, 'index'])->name('index');
    Route::post('/', [AgentTypeController::class, 'store'])->name('store');
    Route::get('{id}', [AgentTypeController::class, 'show'])->name('show');
    Route::put('{id}', [AgentTypeController::class, 'update'])->name('update');
    Route::delete('{id}', [AgentTypeController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [AgentTypeController::class, 'forceDelete'])->name('force-delete');
});


// AGENTES
Route::prefix('agents')->name('agents.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [AgentController::class, 'list'])->name('list'); // Para dropdowns
    Route::get('clients', [AgentController::class, 'clients'])->name('clients');
    Route::get('providers', [AgentController::class, 'providers'])->name('providers');

    Route::get('fetch-ruc/{ruc}', [AgentController::class, 'fetchRUC'])->name('fetch-ruc')->withoutMiddleware('validate.numeric.id');
    Route::get('fetch-dni/{dni}', [AgentController::class, 'fetchDNI'])->name('fetch-dni')->withoutMiddleware('validate.numeric.id');

    Route::get('type/{typeId}', [AgentController::class, 'byType'])->name('by-type');

    Route::get('/', [AgentController::class, 'index'])->name('index');
    Route::post('/', [AgentController::class, 'store'])->name('store');
    Route::get('{id}', [AgentController::class, 'show'])->name('show');
    Route::put('{id}', [AgentController::class, 'update'])->name('update');
    Route::delete('{id}', [AgentController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [AgentController::class, 'forceDelete'])->name('force-delete');
});


// // PRODUCTOS
// Route::prefix('products')->name('products.')->group(function () {
//     Route::post('/', [ProductController::class, 'store'])->name('store');
//     Route::get('/{product}', [ProductController::class, 'show'])->name('show');
//     Route::put('/{product}', [ProductController::class, 'update'])->name('update');
//     Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
// });

// // AGENTES
// Route::prefix('agents')->name('agents.')->group(function () {
//     Route::get('/', [AgentController::class, 'index'])->name('index');
//     Route::post('/', [AgentController::class, 'store'])->name('store');
    
//     // Rutas específicas ANTES de las rutas con parámetros
//     Route::get('fetchRUC/{ruc}', [AgentController::class, 'fetchRUC'])->name('fetch-ruc');
//     Route::get('fetchDNI/{dni}', [AgentController::class, 'fetchDNI'])->name('fetch-dni');
    
//     // Rutas con parámetros AL FINAL
//     Route::get('{agent}', [AgentController::class, 'show'])->name('show');
//     Route::put('{agent}', [AgentController::class, 'update'])->name('update');
//     Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');
// });

// // CATEGORÍAS DE AGENTES
// Route::prefix('agent-categories')->name('agent-categories.')->group(function () {
//     Route::get('/', [AgentCategoryController::class, 'index'])->name('index');
//     Route::post('/', [AgentCategoryController::class, 'store'])->name('store');
//     Route::get('{agentCategory}', [AgentCategoryController::class, 'show'])->name('show');
//     Route::put('{agentCategory}', [AgentCategoryController::class, 'update'])->name('update');
//     Route::delete('{agentCategory}', [AgentCategoryController::class, 'destroy'])->name('destroy');
// });

// // CLIENTES
// Route::prefix('clients')->name('clients.')->group(function () {
//     Route::get('/', [ClientController::class, 'index'])->name('index');
//     Route::post('/', [ClientController::class, 'store'])->name('store');
//     Route::get('by-categories', [ClientController::class, 'getClientsByCategories'])->name('by-categories');
//     Route::put('{client}', [ClientController::class, 'update'])->name('update');
// });

// // PROVEEDORES
// Route::prefix('providers')->name('providers.')->group(function () {
//     Route::get('/', [ProviderController::class, 'index'])->name('index');
//     Route::post('/', [ProviderController::class, 'store'])->name('store');
//     Route::get('{provider}', [ProviderController::class, 'show'])->name('show');
//     Route::put('{provider}', [ProviderController::class, 'update'])->name('update');
//     Route::delete('{provider}', [ProviderController::class, 'destroy'])->name('destroy');
// });

// // VENTAS
// Route::prefix('sales')->name('sales.')->group(function () {
//     Route::get('/', [SalesController::class, 'getSales'])->name('index');
//     Route::post('/', [SalesController::class, 'saveSale'])->name('store');
//     Route::get('by-client-categories', [SalesController::class, 'getSalesByClientCategories'])->name('by-client-categories');
//     Route::get('debts/by-client-categories', [SalesController::class, 'getSalesDebtsByClientCategories'])->name('debts.by-client-categories');
//     Route::get('details/{saleId}', [SalesController::class, 'getSaleDetailsBySaleId'])->name('details');
//     Route::get('detail/{saleId}', [SalesController::class, 'getSaleDetailBySaleId'])->name('detail');
//     Route::put('{saleId}', [SalesController::class, 'updateSale'])->name('update');
// });

// // COMPRAS
// Route::prefix('purchases')->name('purchases.')->group(function () {
//     Route::get('/', [PurchasesController::class, 'getPurchases'])->name('index');
//     Route::post('/', [PurchasesController::class, 'savePurchase'])->name('store');
//     Route::get('by-provider-categories', [PurchasesController::class, 'getPurchasesByProviderCategories'])->name('by-provider-categories');
//     Route::get('debts/by-provider-categories', [PurchasesController::class, 'getPurchasesDebtsByProviderCategories'])->name('debts.by-provider-categories');
//     Route::get('details/{purchaseId}', [PurchasesController::class, 'getPurchaseDetailsBySaleId'])->name('details');
// });

// // TIPOS DE AGENTE
// Route::prefix('agent-types')->name('agent-types.')->group(function () {
//     Route::get('/', [AgentTypeController::class, 'index'])->name('index');
//     Route::post('/', [AgentTypeController::class, 'store'])->name('store');
//     Route::get('{agentType}', [AgentTypeController::class, 'show'])->name('show');
//     Route::put('{agentType}', [AgentTypeController::class, 'update'])->name('update');
//     Route::delete('{agentType}', [AgentTypeController::class, 'destroy'])->name('destroy');
// });

// // TRANSACCIONES
// Route::prefix('transactions')->name('transactions.')->group(function () {
//     Route::get('/', [TransactionDetailController::class, 'index'])->name('index');
//     Route::get('{transaction}', [TransactionDetailController::class, 'show'])->name('show');
//     Route::put('{transaction}', [TransactionDetailController::class, 'update'])->name('update');
//     Route::delete('{transaction}', [TransactionDetailController::class, 'destroy'])->name('destroy');
//     Route::post('payments', [TransactionDetailController::class, 'saveTransactionPaymentAndUpdateDebtByRoute'])->name('payments');
// });

// // DEUDAS
// Route::prefix('debts')->name('debts.')->group(function () {
//     Route::get('/', [DebtsController::class, 'index'])->name('index');
// });