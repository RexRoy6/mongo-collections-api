<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;

class BusinessIdentificationController extends Controller
{
    /**
     * Identify business by key or code
     * This is the ENTRY POINT for Vue.js frontend
     */
    public function identify(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'required|string', // Can be business_key OR business_code
        ]);

        // Try to find business by code (8-digit number)
        $business = null;
        
        if (is_numeric($validated['identifier']) && strlen($validated['identifier']) === 8) {
            // It's likely a business_code
            $business = Business::where('business_code', (int)$validated['identifier'])
                ->active()
                ->first();
        }
        
        // If not found by code, try by key
        if (!$business) {
            $business = Business::where('business_key', $validated['identifier'])
                ->active()
                ->first();
        }

        if (!$business) {
            return response()->json([
                'error' => 'business_not_found',
                'message' => 'Business not found or inactive. Please check your business identifier.',
                'suggestions' => [
                    'Check if business_key or business_code is correct',
                    'Contact support if you believe this is an error'
                ]
            ], 404);
        }

        // Return public business information
        return response()->json([
            'success' => true,
            'message' => 'Business identified successfully',
            'business' => $business->getPublicInfo(),
            'api_context' => [
                'headers' => [
                    'X-Business-Code' => $business->business_code,
                    'X-Business-Key' => $business->business_key,
                ],
                'query_params' => [
                    'business_code' => $business->business_code,
                    'business_key' => $business->business_key,
                ]
            ]
        ], 200);
    }

    /**
     * Validate business context (for frontend to verify)
     */
    public function validateContext(Request $request)
    {
        // This endpoint uses the DetectBusiness middleware
        // It just confirms that business context is correctly set
        
        $business = $request->get('current_business');
        
        if (!$business) {
            return response()->json([
                'valid' => false,
                'error' => 'No business context set'
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'business' => $business->getPublicInfo(),
            'timestamp' => now()->toIso8601String()
        ], 200);
    }
}