<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\createSolicitud;
use  App\Http\Controllers\deleteSolicitud;
use  App\Http\Controllers\readSolicitud;
use App\Http\Controllers\updateSolicitud;
use App\Http\Controllers\AuthClientController;
use App\Http\Controllers\HotelOrderController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminKitchenController;
use App\Http\Controllers\KitchenAuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessIdentificationController;

// ========== ROUTES WITH API KEY VALIDATION ==========
// ALL routes go through ApiSolicitudes first
Route::middleware(['api.solicitudes'])->group(function () {
    
    // ========== PUBLIC ROUTES (No business context needed) ==========
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0'
        ]);
    });

    // Business identification (public, but needs API key)
    Route::post('/identify-business', [BusinessIdentificationController::class, 'identify']);

    // ========== ADMIN ROUTES (No business context needed) ==========
    Route::prefix('admin')->group(function () {
        Route::post('/business', [BusinessController::class, 'createBusiness']);
        Route::get('/businesses', [BusinessController::class, 'listBusinesses']);
        Route::get('/business/{businessUuid}', [BusinessController::class, 'getBusiness']);
        Route::put('/business/{businessUuid}', [BusinessController::class, 'updateBusiness']);
        Route::patch('/business/{businessUuid}/toggle-status', [BusinessController::class, 'toggleBusinessStatus']);

        ///kitchen controller
        Route::post('kitchenUsers/create',[AdminKitchenController::class,'createUser']);
        
        // Other admin routes...
    });

    // ========== BUSINESS-CONTEXT ROUTES ==========
    // These require BOTH API key AND business context
    Route::middleware(['detect.business', 'require.business'])->group(function () {
        
        // Public business info
        Route::get('/business-info', function (Request $request) {
            $business = $request->get('current_business');
            return response()->json([
                'business' => $business->getPublicInfo()
            ]);
        });
        
        // Business menus
        Route::get('/menus', [MenuController::class, 'getMenuByKey']);
        
        // Authentication
        Route::prefix('auth')->group(function () {
            Route::prefix('client')->group(function () {
                Route::post('/login', [AuthClientController::class, 'loginOrRegister']);
                Route::put('/reset-room', [AuthClientController::class, 'resetRoom']);
            });
            
            Route::prefix('kitchen')->group(function () {
                Route::post('/login', [KitchenAuthController::class, 'login']);
                Route::put('/logout', [KitchenAuthController::class, 'logout']);
            });
        });
        
        // Authenticated routes
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::prefix('orders')->group(function () {
                Route::post('/', [HotelOrderController::class, 'create']);
                Route::get('/', [HotelOrderController::class, 'read']);
                Route::put('/cancel', [HotelOrderController::class, 'cancel']);
                Route::get('/kitchen', [HotelOrderController::class, 'listOrders']);
                Route::put('/kitchen/update', [HotelOrderController::class, 'updateOrderStatus']);
            });
        });
        
    
    });

        // Legacy routes
        Route::post('/ticket', [createSolicitud::class, 'store']);
        Route::delete('/ticket/destroy', [deleteSolicitud::class, 'destroy']);
        Route::get('/ticket', [readSolicitud::class, 'read']);
        Route::put('/ticket', [updateSolicitud::class, 'update']);
});