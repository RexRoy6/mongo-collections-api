<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Business;


class KitchenAuthController extends Controller
{
    public function login(Request $request)
    {
        // Get business from middleware
        $business = $request->get('current_business');
        
        if (!$business) {
            return response()->json([
                'error' => 'business_context_required',
                'message' => 'Business context is required for authentication'
            ], 400);
        }

        $validated = $request->validate([
            'number_kitchenNumber' => 'required|int',
            'kitchenUser_key'      => 'required|int',
        ]);

        // Find staff within THIS SPECIFIC BUSINESS
        $staff = User::where('business_uuid', $business->uuid)
            ->where('role', 'kitchen')
            ->where('number_kitchenNumber', $validated['number_kitchenNumber'])
            ->first();

        if (!$staff) {
            return response()->json([
                'error' => 'kitchen_user_not_found',
                'message' => 'Kitchen user not found in this business'
            ], 404);
        }

        if ($staff->kitchenUser_key !== $validated['kitchenUser_key']) {
            return response()->json([
                'error' => 'invalid_key',
                'message' => 'Invalid kitchen user key'
            ], 403);
        }

        // If already logged in return existing kitchen uuid
        if ($staff->is_active && $staff->kitchenUser_uuid) {
            return response()->json([
                'error' => 'already_logged_in',
                'message' => 'Kitchen staff already logged in',
                'kitchenUser_uuid'  => $staff->kitchenUser_uuid,
                'business' => $business->getPublicInfo()
            ], 422);
        }

        $staff->activateKitchenUser();

        // Create or update kitchen auth user WITH BUSINESS CONTEXT
        $authUser = \App\Models\kitchenAuthUser::updateOrCreate(
            [
                'kitchenUser_uuid' => $staff->kitchenUser_uuid,
                'business_uuid' => $business->uuid, // Add business context
                'business_key' => $business->business_key
            ],
            [
                'name_kitchenUser'  => $staff->name_kitchenUser,
            ]
        );

        // Create token with business scope
        $token = $authUser->createToken('kitchen-token', [
            'kitchen:basic',
            'business:' . $business->business_key
        ])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 43200,
            'business' => $business->getPublicInfo(),
            'kitchen_user' => [
                'message'     => 'Kitchen user logged in',
                'kitchenUser_uuid'  => $staff->kitchenUser_uuid,
                'name_kitchenUser'  => $staff->name_kitchenUser,
                'number_kitchenNumber' => $staff->number_kitchenNumber
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        // Get business from middleware
        $business = $request->get('current_business');
        
        if (!$business) {
            return response()->json([
                'error' => 'business_context_required',
                'message' => 'Business context is required'
            ], 400);
        }

        $validated = $request->validate([
            'number_kitchenNumber' => 'required|int',
            'kitchenUser_key'      => 'required|int',
        ]);

        // Find staff within THIS SPECIFIC BUSINESS
        $staff = User::where('business_uuid', $business->uuid)
            ->where('role', 'kitchen')
            ->where('number_kitchenNumber', $validated['number_kitchenNumber'])
            ->first();

        if (!$staff) {
            return response()->json([
                'error' => 'kitchen_user_not_found',
                'message' => 'Kitchen user not found in this business'
            ], 404);
        }

        if ($staff->kitchenUser_uuid == null || $staff->is_active == false) {
            return response()->json([
                'error' => 'already_logged_out',
                'message' => 'Kitchen user already logged out'
            ], 422);
        }

        if ($staff->kitchenUser_key !== $validated['kitchenUser_key']) {
            return response()->json([
                'error' => 'invalid_key',
                'message' => 'Invalid kitchen user key'
            ], 403);
        }

        $staff->deactivateKitchenUser();

        return response()->json([
            'success' => true,
            'message' => 'Kitchen user logged out successfully',
            'business' => $business->getPublicInfo(),
            'kitchen_user' => [
                'number_kitchenNumber' => $staff->number_kitchenNumber,
                'name_kitchenUser' => $staff->name_kitchenUser,
                'is_active' => $staff->is_active
            ]
        ], 200);
    }
}