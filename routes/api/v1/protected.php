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
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\TransactionTypeController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

// AUTENTICACIÓN PROTEGIDA
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('revoke-all', [AuthController::class, 'revokeAllTokens'])->name('revoke-all');
    Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
});

// USUARIOS (GESTIÓN ADMINISTRATIVA)
Route::prefix('users')->name('users.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [UserController::class, 'list'])->name('list'); // Para dropdowns

    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('{id}', [UserController::class, 'show'])->name('show');
    Route::put('{id}', [UserController::class, 'update'])->name('update');                    // Para JSON
    Route::post('{id}', [UserController::class, 'update'])->name('update-form');             // Para formularios
    Route::delete('{id}', [UserController::class, 'destroy'])->name('destroy');

    Route::delete('{id}/force', [UserController::class, 'forceDelete'])->name('force-delete');
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

// MÉTODOS DE PAGO
Route::prefix('payment-methods')->name('payment-methods.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [PaymentMethodController::class, 'list'])->name('list'); // Para dropdowns
    Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
    Route::get('{id}', [PaymentMethodController::class, 'show'])->name('show');
});

// TIPOS DE TRANSACCIÓN
Route::prefix('transaction-types')->name('transaction-types.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [TransactionTypeController::class, 'list'])->name('list'); // Para dropdowns
    Route::get('/', [TransactionTypeController::class, 'index'])->name('index');
    Route::get('{id}', [TransactionTypeController::class, 'show'])->name('show');
});

// PRECIOS POR ZONA
Route::prefix('product-price-details')->name('product-price-details.')->middleware('validate.numeric.id')->group(function () {
    Route::get('list', [ProductPriceDetailController::class, 'list'])->name('list'); // Para dropdowns
    Route::get('flat', [ProductPriceDetailController::class, 'flat'])->name('flat')  // ✅ NUEVO: Lista plana
        ->withoutMiddleware('validate.numeric.id');
    
    // ✅ GESTIÓN MASIVA
    Route::post('massive', [ProductPriceDetailController::class, 'storeMassive'])->name('store-massive')
        ->withoutMiddleware('validate.numeric.id');
    Route::put('massive', [ProductPriceDetailController::class, 'updateMassive'])->name('update-massive')
        ->withoutMiddleware('validate.numeric.id');

    // ✅ POR PRODUCTO
    Route::get('product/{productId}/zones', [ProductPriceDetailController::class, 'getProductZonePrices'])
        ->name('product-zones')->where('productId', '[0-9]+');
    Route::delete('product/{productId}/clear', [ProductPriceDetailController::class, 'clearProductPrices'])
        ->name('clear-product-prices')->where('productId', '[0-9]+');

    // ✅ CRUD ESTÁNDAR
    Route::get('/', [ProductPriceDetailController::class, 'index'])->name('index'); // 👈 AGRUPADO POR PRODUCTO
    Route::get('{id}', [ProductPriceDetailController::class, 'show'])->name('show');
    Route::put('{id}', [ProductPriceDetailController::class, 'update'])->name('update');
    Route::delete('{id}', [ProductPriceDetailController::class, 'destroy'])->name('destroy');
    Route::delete('{id}/force', [ProductPriceDetailController::class, 'forceDelete'])->name('force-delete');
});

// VIAJES (CON PERMISOS POR ROL) - CORREGIR MIDDLEWARE
Route::prefix('trips')->name('trips.')->group(function () {
    Route::get('list', [TripController::class, 'list'])->name('list'); // Para dropdowns
    
    Route::get('/', [TripController::class, 'index'])->name('index');
    Route::post('/', [TripController::class, 'store'])->name('store'); // ✅ SIN MIDDLEWARE RESTRICTIVO
    
    // APLICAR MIDDLEWARE SOLO A RUTAS CON ID
    Route::middleware('validate.numeric.id')->group(function () {
        Route::get('{id}', [TripController::class, 'show'])->name('show');
        Route::get('{id}/dependencies', [TripController::class, 'dependencies'])->name('dependencies');
        Route::put('{id}', [TripController::class, 'update'])->name('update');
        Route::delete('{id}', [TripController::class, 'destroy'])->name('destroy');
        Route::delete('{id}/force', [TripController::class, 'forceDelete'])->name('force-delete');
        Route::delete('{id}/force-direct', [TripController::class, 'forceDeleteDirect'])->name('force-delete-direct');
    });
});

// TRANSACCIONES (CON PERMISOS POR ROL)
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('list', [TransactionController::class, 'list'])->name('list');
    
    // ✅ RUTAS ESPECIALES SIN MIDDLEWARE ID
    Route::get('sales', [TransactionController::class, 'sales'])->name('sales');
    Route::get('purchases', [TransactionController::class, 'purchases'])->name('purchases');
    Route::get('pending-delivery', [TransactionController::class, 'pendingDelivery'])->name('pending-delivery');
    Route::get('pending-payment', [TransactionController::class, 'pendingPayment'])->name('pending-payment');
    Route::get('completed', [TransactionController::class, 'completed'])->name('completed');
    
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::post('/', [TransactionController::class, 'store'])->name('store');
    
    // ✅ APLICAR MIDDLEWARE SOLO A RUTAS CON ID
    Route::middleware('validate.numeric.id')->group(function () {
        Route::get('{id}', [TransactionController::class, 'show'])->name('show');
        Route::get('{id}/dependencies', [TransactionController::class, 'dependencies'])->name('dependencies');
        
        // ✅ NUEVA RUTA: Obtener datos para crear devolución
        Route::get('{id}/return-data', [TransactionController::class, 'getReturnData'])->name('return-data');
        
        Route::put('{id}', [TransactionController::class, 'update'])->name('update');
        Route::delete('{id}', [TransactionController::class, 'destroy'])->name('destroy');
        Route::delete('{id}/force', [TransactionController::class, 'forceDelete'])->name('force-delete');
        
        // ✅ ACCIONES ESPECÍFICAS DE TRANSACCIONES
        Route::patch('{id}/deliver', [TransactionController::class, 'markAsDelivered'])->name('deliver');
        Route::patch('{id}/return', [TransactionController::class, 'markAsReturned'])->name('return');
        Route::patch('{id}/cancel', [TransactionController::class, 'cancelTransaction'])->name('cancel');
        Route::post('{id}/add-payment', [TransactionController::class, 'addPayment'])->name('add-payment');
        
        // ✅ NUEVA RUTA: Verificar si se puede cancelar
        Route::get('{id}/can-cancel', [TransactionController::class, 'canCancel'])->name('can-cancel');
    });
    
    // ✅ RUTAS PARA DEVOLUCIONES ACTIVAS
    Route::post('returns', [TransactionController::class, 'createReturn'])->name('create-return');
    Route::get('returns', [TransactionController::class, 'returns'])->name('returns'); // Solo activas
    Route::get('{id}/returns', [TransactionController::class, 'getTransactionReturns'])
        ->name('transaction-returns')
        ->middleware('validate.numeric.id');
});
