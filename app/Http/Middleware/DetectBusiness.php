<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Business;
use Symfony\Component\HttpFoundation\Response;

class DetectBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try multiple ways to identify the business
        $businessIdentifier = $this->getBusinessIdentifier($request);
        
        if (!$businessIdentifier) {
            // No business identifier provided, continue without business context
            // (for routes that don't need it, like admin/business creation)
            return $next($request);
        }

        // Find the business
        $business = $this->findBusiness($businessIdentifier);
        
        if (!$business) {
            return response()->json([
                'error' => 'Business not found',
                'message' => 'The specified business does not exist'
            ], 404);
        }

        if (!$business->is_active) {
            return response()->json([
                'error' => 'Business inactive',
                'message' => 'This business account is currently inactive'
            ], 403);
        }

        // Store business in request and app container
        $request->merge(['current_business' => $business]);
        app()->instance('current_business', $business);

        return $next($request);
    }

    private function getBusinessIdentifier(Request $request)
    {
        // Check in this order:
        // 1. X-Business-Code header
        // 2. X-Business-Key header  
        // 3. business_code query parameter
        // 4. business_key query parameter
        // 5. Subdomain (if using subdomains)

        if ($request->hasHeader('X-Business-Code')) {
            return ['type' => 'code', 'value' => $request->header('X-Business-Code')];
        }

        if ($request->hasHeader('X-Business-Key')) {
            return ['type' => 'key', 'value' => $request->header('X-Business-Key')];
        }

        if ($request->has('business_code')) {
            return ['type' => 'code', 'value' => $request->query('business_code')];
        }

        if ($request->has('business_key')) {
            return ['type' => 'key', 'value' => $request->query('business_key')];
        }

        // If using subdomains (e.g., cafe.lunas.example.com)
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return ['type' => 'key', 'value' => $subdomain];
        }

        return null;
    }

    private function findBusiness($identifier)
    {
        if ($identifier['type'] === 'code') {
            return Business::where('business_code', $identifier['value'])->first();
        }
        
        if ($identifier['type'] === 'key') {
            return Business::where('business_key', $identifier['value'])->first();
        }
        
        return null;
    }
}