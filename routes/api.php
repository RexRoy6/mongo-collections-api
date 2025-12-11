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
use App\Http\Controllers\BusinessIdentificationController; // Add this!

// ========== PUBLIC ROUTES (No middleware, No business context needed) ==========

// Health check - completely public
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0'
    ]);
});

// Business identification - ENTRY POINT for Vue.js (MUST be public)
Route::post('/identify-business', [BusinessIdentificationController::class, 'identify']);

// Business context validation (optional, for frontend to verify)
Route::get('/validate-business', [BusinessIdentificationController::class, 'validateContext']);

// ========== ADMIN BUSINESS MANAGEMENT ==========
// These routes don't need business context but might need authentication
Route::prefix('admin')->middleware(['api.solicitudes'])->group(function () {
    Route::post('/business', [BusinessController::class, 'createBusiness']);
    Route::get('/businesses', [BusinessController::class, 'listBusinesses']);
    Route::get('/business/{businessUuid}', [BusinessController::class, 'getBusiness']);
    Route::put('/business/{businessUuid}', [BusinessController::class, 'updateBusiness']);
    Route::patch('/business/{businessUuid}/toggle-status', [BusinessController::class, 'toggleBusinessStatus']);
    
    // Other admin routes...
});

// ========== BUSINESS-CONTEXT ROUTES ==========
// These routes require business identification
// They go through: api.solicitudes → detect.business → require.business
Route::middleware(['api.solicitudes', 'require.business'])->group(function () {
    
    // Public business info (after business is identified)
    Route::get('/business-info', function (Request $request) {
        $business = $request->get('current_business');
        return response()->json([
            'business' => $business->getPublicInfo()
        ]);
    });
    
    // Business-specific menu routes
    Route::get('/menus', [MenuController::class, 'getMenuByKey']);
    
    // Business-specific authentication
    Route::prefix('auth')->group(function () {
        // Client auth for this business
        Route::prefix('client')->group(function () {
            Route::post('/login', [AuthClientController::class, 'loginOrRegister']);
            Route::put('/reset-room', [AuthClientController::class, 'resetRoom']);
        });
        
        // Kitchen auth for this business
        Route::prefix('kitchen')->group(function () {
            Route::post('/login', [KitchenAuthController::class, 'login']);
            Route::put('/logout', [KitchenAuthController::class, 'logout']);
        });
    });
    
    // Authenticated routes with business context
    Route::middleware(['auth:sanctum'])->group(function () {
        // Orders for current business
        Route::prefix('orders')->group(function () {
            Route::post('/', [HotelOrderController::class, 'create']);
            Route::get('/', [HotelOrderController::class, 'read']);
            Route::put('/cancel', [HotelOrderController::class, 'cancel']);
            
            // Kitchen routes for current business
            Route::get('/kitchen', [HotelOrderController::class, 'listOrders']);
            Route::put('/kitchen/update', [HotelOrderController::class, 'updateOrderStatus']);
        });
        
        // Add other business-scoped routes here...
    });

    // ========== LEGACY ROUTES (For backward compatibility) ==========
    // Keep your existing routes but wrap them in business context
    Route::post('/ticket', [createSolicitud::class, 'store']);
    Route::delete('/ticket/destroy', [deleteSolicitud::class, 'destroy']);
    Route::get('/ticket', [readSolicitud::class, 'read']);
    Route::put('/ticket', [updateSolicitud::class, 'update']);
    
    // Other legacy routes if any...
});