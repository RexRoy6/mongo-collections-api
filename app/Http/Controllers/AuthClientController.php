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
            'room_number' => 'required|string',
            'room_key'    => 'required|string',
        ]);

        $room = User::where('role', 'client')
            ->where('room_number', $validated['room_number'])
            ->where('room_key', $validated['room_key'])
            ->first();

        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        $room->resetRoom();

        return response()->json(['message' => 'Room reset successfully'], 200);
    }
}
