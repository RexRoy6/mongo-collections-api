<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Use Laravel's built-in CORS middleware
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Register your custom middleware aliases
        $middleware->alias([
            'detect.business' => \App\Http\Middleware\DetectBusiness::class,
            'require.business' => \App\Http\Middleware\RequireBusiness::class,
            'api.solicitudes' => \App\Http\Middleware\ApiSolicitudes::class, // Your existing middleware
        ]);

        // Add middleware to API group
        // Order matters: CORS first, then business detection, then Sanctum
        $middleware->api(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\DetectBusiness::class, // Add this here
            \App\Http\Middleware\ApiSolicitudes::class,
        ]);

        // If you want DetectBusiness on ALL API requests (recommended)
        // $middleware->appendToGroup('api', [
        //     \App\Http\Middleware\DetectBusiness::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();