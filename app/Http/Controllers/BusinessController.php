<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;


class BusinessController extends Controller
{

/**
     * Create a new business
     */
    public function createBusiness(Request $request)
    {
        $validated = $request->validate([
            'business_key'  => 'required|string',
            'business_info' => 'nullable|string',
            'is_active'     => 'boolean',
            'config'        => 'nullable|array',
            'public_config' => 'nullable|array', // Added this
        ]);

        // Check if business already exists
        $exists = Business::where('business_key', $validated['business_key'])->first();

        if ($exists) {
            return response()->json([
                'message' => 'Business already exists'
            ], 422);
        }

        // Set default public config if not provided
        if (!isset($validated['public_config'])) {
            $validated['public_config'] = [
                'theme' => 'default',
                'primary_color' => '#4a90e2',
                'secondary_color' => '#f5a623',
                'welcome_message' => "Welcome to {$validated['business_info']}",
            ];
        }

        // Create business - code will be auto-generated
        $business = Business::create($validated);

        return response()->json([
            'message' => 'Business created successfully',
            'business' => $business->getPublicInfo(),
            'access_info' => [
                'business_code' => $business->business_code,
                'business_key' => $business->business_key,
                'identification_url' => config('app.url') . '/identify-business',
                'headers_example' => [
                    'X-Business-Code' => $business->business_code,
                    'X-Business-Key' => $business->business_key
                ]
            ]
        ], 201);
    }

    //falta agregar put /updateBusiness/listBusinesse

// In BusinessController
public function identify(Request $request)
{
    $validated = $request->validate([
        'business_key' => 'required|string',  // or business_code
    ]);

    $business = Business::where('business_key', $validated['business_key'])
        ->orWhere('business_code', $validated['business_code'] ?? null)
        ->active()  // Only active businesses
        ->first();

    if (!$business) {
        return response()->json([
            'error' => 'Business not found or inactive'
        ], 404);
    }

    return response()->json([
        'business' => [
            'name' => $business->business_info,
            'key' => $business->business_key,
            'code' => $business->business_code,
            'config' => $business->config,
        ]
    ]);
}

}
