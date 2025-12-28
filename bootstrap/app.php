<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
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

            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        // Add middleware to API group
        // Order matters: CORS first, then business detection, then Sanctum
        $middleware->api(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\ApiSolicitudes::class,
            \App\Http\Middleware\DetectBusiness::class, // Add this here


        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
