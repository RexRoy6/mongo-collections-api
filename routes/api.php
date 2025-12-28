<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\createSolicitud;
use  App\Http\Controllers\deleteSolicitud;
use  App\Http\Controllers\readSolicitud;
use App\Http\Controllers\updateSolicitud;
use App\Http\Controllers\HotelOrderController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessIdentificationController;
use App\Http\Controllers\AuthController;
use App\Events\OrderCreated;


// ========== ROUTES WITH API KEY VALIDATION ==========
// ALL routes go through ApiSolicitudes first
Route::middleware(['api.solicitudes'])->group(function () {

    // ========== PUBLIC ROUTES (No business context needed) ==========
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.1'
        ]);
    });

    Route::get('/test-broadcast', function () {
    OrderCreated::dispatch(\App\Models\Order::first());
    return 'broadcast sent';
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

        ///crear cocina hotel y cuartos de hotel
        Route::post('createMenu', [AdminController::class, 'createMenu']);
        //create users for other business
        Route::post('createUser', [AdminController::class, 'create_user']);


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

        // Authentication refactor
        Route::prefix('auth')->group(function () {
            Route::post('/client/login', [AuthController::class, 'clientLogin']);
            Route::put('/client/reset-room', [AuthController::class, 'clientResetRoom']);

            Route::post('/staff/login', [AuthController::class, 'staffLogin']);
            Route::put('/staff/logout', [AuthController::class, 'staffLogout']);
        });

        // Authenticated routes for business: hotel/cafe etc
        Route::middleware(['auth:sanctum'])->group(function () {

            Route::prefix('orders')->group(function () {

                // CREATE (client, barista)
                Route::post('/')
                    ->middleware('abilities:orders:create')
                    ->uses([HotelOrderController::class, 'create']);

                // READ (client, kitchen, barista, admin)
                Route::get('/')
                    ->middleware('abilities:orders:read')
                    ->uses([HotelOrderController::class, 'read']);

                // CANCEL (client only)
                Route::put('/cancel')
                    ->middleware('abilities:orders:cancel')
                    ->uses([HotelOrderController::class, 'cancel']);

                // KITCHEN / BARISTA LIST (today)
                Route::get('/kitchen')
                    ->middleware('abilities:orders:read')
                    ->uses([HotelOrderController::class, 'listOrders']);

                // UPDATE STATUS
                Route::put('/kitchen/update')
                    ->middleware('abilities:orders:update')
                    ->uses([HotelOrderController::class, 'updateOrderStatus']);

                //update items/. only for barista:
                Route::put('/kitchen/updateItems')
                    ->middleware('abilities:orders:update')
                    ->uses([HotelOrderController::class, 'updateOrderItems']);



            });
        });
    });

    // Legacy routes
    Route::post('/ticket', [createSolicitud::class, 'store']);
    Route::delete('/ticket/destroy', [deleteSolicitud::class, 'destroy']);
    Route::get('/ticket', [readSolicitud::class, 'read']);
    Route::put('/ticket', [updateSolicitud::class, 'update']);
});
