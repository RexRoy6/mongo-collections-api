<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * Create one or multiple hotel rooms.
     * Accepts single object or array of rooms.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'rooms' => 'required|array|min:1',
            'rooms.*.room_number' => 'required|int',
            'rooms.*.room_key'    => 'required|int',
        ]);

        $createdRooms = [];

        foreach ($validated['rooms'] as $roomData) {

            // Check if room already exists
            $exists = User::where('role', 'client')
                ->where('room_number', $roomData['room_number'])
                ->first();

            if ($exists) {
                // Skip existing room but add info to response
                $createdRooms[] = [
                    'room_number' => $roomData['room_number'],
                    'status'      => 'already exists'
                ];
                continue;
            }

            // Create room
            $room = new User([
                'role'        => 'client',
                'room_number' => $roomData['room_number'],
                'room_key'    => $roomData['room_key'],
                'guest_uuid'  => null,
                'guest_name'  => null,
                'is_occupied' => false,
            ]);

            $room->save();

            $createdRooms[] = [
                'room_number' => $room->room_number,
                'status'      => 'created'
            ];
        }

        return response()->json([
            'message' => 'Room creation processed',
            'rooms'   => $createdRooms
        ], 200);
    }
}
