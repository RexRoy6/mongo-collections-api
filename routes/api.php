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

Route::middleware('api.solicitudes')->group(function () {

//ruta de cracion:
Route::post('/ticket', [createSolicitud::class, 'store']);



//rutas metodo delete
//esta ruta es concuidado , destuye un registro por su uuid, no usar mas que para limpiar
Route::delete('/ticket/destroy', [deleteSolicitud::class, 'destroy']);

//ruta para el metodo get:
Route::get('/ticket', [readSolicitud::class, 'read']);

//ruta para modificar el status history:
Route::put('/ticket', [updateSolicitud::class, 'update']);


#admin routes only
Route::post('/admin/rooms/create', [AdminController::class, 'create']);
Route::post('/admin/kitchenUsers/create', [AdminKitchenController::class, 'create']);
Route::post('/admin/createMenu', [AdminController::class, 'createMenu']);
//Route::get('/admin/menu/{menu_key}', [AdminController::class, 'getMenu']);


#client  only
Route::prefix('auth/client')->group(function () {
    Route::post('/login', [AuthClientController::class, 'loginOrRegister']);
    Route::put('/reset-room',      [AuthClientController::class, 'resetRoom']);
});

 // AUTHENTICATED GUEST ROUTES
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/hotel/orders', [HotelOrderController::class, 'create']);
        Route::get('/hotel/orders', [HotelOrderController::class, 'read']);
        Route::put('/hotel/orders', [HotelOrderController::class, 'cancel']);

    });

// Client-facing menu endpoints
Route::get('/hotel/menus', [MenuController::class, 'getMenuByKey']);



##kitchen only
Route::prefix('auth/kitchen')->group(function () {
Route::post('/login', [KitchenAuthController::class, 'login']);
Route::put('/logout', [KitchenAuthController::class, 'logout']);
});


 // AUTHENTICATED kitchen ROUTES
    Route::middleware('auth:sanctum')->group(function () {
Route::get('kitchen/orders', [HotelOrderController::class, 'listOrders']);
Route::put('kitchen/ordersUpdate', [HotelOrderController::class, 'updateOrderStatus']);
   });


});
