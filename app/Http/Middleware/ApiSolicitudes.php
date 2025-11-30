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
        $apiKeyF = $request->header('API-KEY') || $request->header('x-api-key');
        $envApiKey = env('API_KEY');
        if ($apiKeyF == $envApiKey) {
            return $next($request);
        }

        return response($content = 'API key inválida',$status =  401);
    }
}
