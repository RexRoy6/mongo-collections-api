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
        // Try to find business identifier
        $businessIdentifier = $this->getBusinessIdentifier($request);
        
        // If no identifier provided, just continue
        if (!$businessIdentifier) {
            return $next($request);
        }

        // Try to find the business
        $business = $this->findBusiness($businessIdentifier);
        
        // If business found and active, set context
        if ($business && $business->is_active) {
            $request->merge(['current_business' => $business]);
            app()->instance('current_business', $business);
        }
        
        // IMPORTANT: Continue even if business not found
        // Some routes (like /admin/business) don't need business context
        return $next($request);
    }

    private function getBusinessIdentifier(Request $request)
    {
        // Check in this order:
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

        // If using subdomains
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