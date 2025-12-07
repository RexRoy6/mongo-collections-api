<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthClientController extends Controller
{
    /**
     * 1) ROOM LOGIN (room number + room key)
     * Steps:
     * - Find user with role=client
     * - Verify room + key
     * - Return guest_uuid if room already occupied
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|string',
            'room_key'    => 'required|string'
        ]);

        $room = User::where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();

        if (!$room) {
            return response()->json(['message' => 'Invalid room or key'], 404);
        }

        // If room already occupied → return existing guest_uuid
        if ($room->is_occupied && $room->guest_uuid) {
            return response()->json([
                'message'     => 'Room already logged in',
                'guest_uuid'  => $room->guest_uuid,
                'guest_name'  => $room->guest_name ?? null,
                'room_number' => $room->room_number
            ], 200);
        }

        return response()->json([
            'message' => 'Room validated. Please register guest name.'
        ], 200);
    }

    /**
     * 2) REGISTER NAME (after login)
     * Assign guest_name + guest_uuid
     */
    public function registerName(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|string',
            'room_key'    => 'required|string',
            'guest_name'  => 'required|string'
        ]);

        $room = User::where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();

        if (!$room) {
            return response()->json(['message' => 'Invalid room'], 404);
        }

        // Assign guest
        $room->assignGuest($validated['guest_name']);

        return response()->json([
            'message'     => 'Guest assigned',
            'guest_uuid'  => $room->guest_uuid,
            'guest_name'  => $room->guest_name,
            'room_number' => $room->room_number
        ], 200);
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
            return response()->json(['message' => 'Room has no client/user assigned'], 200);

        }

        $room->resetRoom();

        return response()->json(['message' => 'Room reset successfully'], 200);
    }

    public function loginOrRegister(Request $request)
{
    $validated = $request->validate([
        'room_number' => 'required|int',
        'room_key'    => 'required|int',
        'guest_name'  => 'nullable|string' // only needed if registering
    ]);

    // Find room
    $room = User::where('role', 'client')
        ->where('room_number', $validated['room_number'])
        ->first();

    if (!$room) {
        return response()->json(['message' => 'Room not found'], 404);
    }

    // Check key
    if ($room->room_key !== $validated['room_key']) {
        return response()->json(['message' => 'Invalid room key'], 403);
    }

    // If already occupied → return existing guest
    if ($room->is_occupied && $room->guest_uuid) {
        return response()->json([
            'message'     => 'Room already occupied',
            'guest_uuid'  => $room->guest_uuid
            //'guest_name'  => $room->guest_name,
            //'room_number' => $room->room_number
        ], 200);
    }

    // If room is empty → register guest
    if (!$room->is_occupied) {

        if (!$validated['guest_name']) {
            return response()->json([
                'message' => 'guest_name is required for new guest'
            ], 422);
        }

        $room->assignGuest($validated['guest_name']);

        return response()->json([
            'message'     => 'Guest registered',
            'guest_uuid'  => $room->guest_uuid,
            'guest_name'  => $room->guest_name,
            'room_number' => $room->room_number
        ], 200);
    }
}

}
