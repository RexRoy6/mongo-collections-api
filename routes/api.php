<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\createSolicitud;
use  App\Http\Controllers\deleteSolicitud;
use  App\Http\Controllers\readSolicitud;
use App\Http\Controllers\updateSolicitud;

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
});
