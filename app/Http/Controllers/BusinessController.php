<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;


class BusinessController extends Controller
{

    public function createBusiness(Request $request)
    {
        $validated = $request->validate([
            'business_key'  => 'required|string',
            'business_info' => 'nullable|string',
        ]);
            $exists = Business::where('business_key',$validated['business_key'])
                ->first();

            if ($exists) {
                return response()->json([
            'message' => 'Business already exist'
        ], 422);
               
            }
        // Create business - code will be auto-generated
        $business = Business::create($validated);

        return response()->json([
            'message' => 'Business created successfully',
            'business' => $business
        ], 200);
    }


}
