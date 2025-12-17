<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSolicitudes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from either header
        $apiKey = $request->header('API-KEY') ?? $request->header('x-api-key');
        $envApiKey = env('API_KEY');
        
        // Check if API key is provided and matches
        if (!$apiKey || $apiKey !== $envApiKey) {
            return response()->json([
                'error' => 'invalid_api_key',
                'message' => 'API key inválida o no proporcionada'
            ], 401);
        }

        return $next($request);
    }
}