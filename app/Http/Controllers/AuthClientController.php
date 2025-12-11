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
            'room_key' => 'required|integer',
            'guest_name' => 'required|string',
        ]);

        // Find user within THIS SPECIFIC BUSINESS
        $user = User::where('business_uuid', $business->uuid)
            ->where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();
        
        if (!$user) {
            // Create new user FOR THIS BUSINESS
            $user = User::create([
                'business_uuid' => $business->uuid, // Critical: business association
                'role' => 'client',
                ...$validated
            ]);
        }
        
        // Assign guest to room
        $user->assignGuest($validated['guest_name']);
        
        // Create token with business scope
        $token = $user->createToken('client-token', [
            'client:basic',
            'business:' . $business->business_key
        ])->plainTextToken;
        
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'business' => $business->getPublicInfo(),
            'guest' => [
                'name' => $user->guest_name,
                'room' => $user->room_number,
                'uuid' => $user->guest_uuid
            ]
        ]);
    }


     /**
     * 3) RESET ROOM
     */
    public function resetRoom(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|int',
            'room_key'    => 'required|int',
        ]);

        $room = User::where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        
        }elseif($room->guest_name == null || $room->is_occupied = false){
            return response()->json(['message' => 'Room has no client/user assigned'], 422);

        }

        $room->resetRoom();

        return response()->json(['message' => 'Room reset successfully'], 200);
    }
    
}