<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Business;

class AuthClientController extends Controller
{
   public function loginOrRegister(Request $request)
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
        'room_number' => 'required|integer',
        'room_key'    => 'required|integer',
        'guest_name'  => 'nullable|string' // only needed if registering
    ]);

    // Find room WITHIN THIS SPECIFIC BUSINESS
    $room = User::where('business_uuid', $business->uuid)
        ->where('role', 'client')
        ->where('room_number', $validated['room_number'])
        ->first();

    if (!$room) {
        return response()->json([
            'error' => 'room_not_found',
            'message' => 'Room not found in this business'
        ], 404);
    }

    // Check key
    if ($room->room_key != $validated['room_key']) {
        return response()->json([
            'error' => 'invalid_room_key',
            'message' => 'Invalid room key'
        ], 403);
    }

    // If already occupied → return existing guest info
    if ($room->is_occupied && $room->guest_uuid) {
        return response()->json([
            'error' => 'room_already_occupied',
            'message' => 'Room already occupied',
            'guest_uuid' => $room->guest_uuid,
            'guest_name' => $room->guest_name,
            'room_number' => $room->room_number,
            'business' => $business->getPublicInfo()
        ], 422);
    }

    // If room is empty → register new guest
    if (!$room->is_occupied) {

        if (empty($validated['guest_name'])) {
            return response()->json([
                'error' => 'guest_name_required',
                'message' => 'guest_name is required for new guest registration'
            ], 422);
        }

        // Assign guest to room
        $room->assignGuest($validated['guest_name']);

        // Create or update GuestAuthUser with business context
        $authUser = \App\Models\GuestAuthUser::updateOrCreate(
            [
                'guest_uuid' => $room->guest_uuid,
                'business_uuid' => $business->uuid,
                'business_key' => $business->business_key
            ],
            [
                'guest_name'  => $room->guest_name,
                'room_number' => $room->room_number,
            ]
        );

        // Create token with business scope
        $token = $authUser->createToken('guest-token', [
            'guest:basic',
            'business:' . $business->business_key
        ])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => 14400,
            'business' => $business->getPublicInfo(),
            'guest' => [
                'message'     => 'Guest registered successfully',
                'guest_uuid'  => $room->guest_uuid,
                'guest_name'  => $room->guest_name,
                'room_number' => $room->room_number
            ]
        ], 200);
    }

    // Fallback - should never reach here
    return response()->json([
        'error' => 'unknown_error',
        'message' => 'An unexpected error occurred'
    ], 500);
}

     /**
     * 3) RESET ROOM
     */
    public function resetRoom(Request $request)
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
            'room_number' => 'required|int',
            'room_key'    => 'required|int',
        ]);

        // Find room within THIS SPECIFIC BUSINESS
        $room = User::where('business_uuid', $business->uuid)
            ->where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();

        if (!$room) {
            return response()->json([
                'error' => 'room_not_found',
                'message' => 'Room not found in this business'
            ], 404);
        
        } elseif($room->guest_name == null || $room->is_occupied == false){
            return response()->json([
                'error' => 'room_not_occupied',
                'message' => 'Room has no client/user assigned'
            ], 422);

        }

        $room->resetRoom();

        return response()->json([
            'success' => true,
            'message' => 'Room reset successfully',
            'business' => $business->getPublicInfo(),
            'room' => [
                'room_number' => $room->room_number,
                'is_occupied' => $room->is_occupied,
                'guest_name' => $room->guest_name
            ]
        ], 200);
    }
    
}