<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\Log;


class BusinessController extends Controller
{

/**
     * Create a new business
     */
    public function createBusiness(Request $request)
    {

        try{

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
        //dd($validated);

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

        } catch (\Exception $e) {

            Log::error("Error creating solicitud", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }

       
    }

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

   /**
     * Update business information
     */
    public function updateBusiness(Request $request, $businessUuid)
    {
        try{
             $business = Business::where('uuid', $businessUuid)->firstOrFail();

        $validated = $request->validate([
            'business_info' => 'nullable|string',
            'config'        => 'nullable|array',
            'public_config' => 'nullable|array'
        ]);

        $business->update($validated);

        return response()->json([
            'message' => 'Business updated successfully',
            'business' => $business
        ], 200);

        }catch (\Exception $e) {

            Log::error("Error updateBusiness", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

        /**
     * List all businesses (for admin)
     */
    public function listBusinesses(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        
        $businesses = Business::paginate($perPage);

        return response()->json([
            'businesses' => $businesses,
            'meta' => [
                'total' => $businesses->total(),
                'per_page' => $businesses->perPage(),
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
            ]
        ], 200);
    }

    /**
     * Get business details
     */
    public function getBusiness($businessUuid)
    {
        $business = Business::where('uuid', $businessUuid)->firstOrFail();

        return response()->json([
            'business' => $business
        ], 200);
    }

    /**
     * Toggle business active status
     */
    public function toggleBusinessStatus($businessUuid)
    {
        $business = Business::where('uuid', $businessUuid)->firstOrFail();

        $business->update([
            'is_active' => !$business->is_active
        ]);

        return response()->json([
            'message' => 'Business status updated',
            'business' => $business
        ], 200);
    }

}
