<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->has('current_business')) {
            return response()->json([
                'error' => 'Business context required',
                'message' => 'Please provide business identifier. Use headers: X-Business-Code or X-Business-Key',
                'example_headers' => [
                    'X-Business-Code' => '80123456',
                    'X-Business-Key' => 'lunas_cafe'
                ]
            ], 400);
        }

        return $next($request);
    }
}