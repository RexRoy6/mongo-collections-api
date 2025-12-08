<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthClientController extends Controller
{
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
       // dd($room);

          //generarle su bearer token nene
        // $token = $room->createToken('guest-token', ['guest:basic'])->plainTextToken;
        // create or update SQL auth entry
$authUser = \App\Models\GuestAuthUser::updateOrCreate(
    ['guest_uuid' => $room->guest_uuid],
    [
        'guest_name'  => $room->guest_name,
        'room_number' => $room->room_number,
    ]
);

$token = $authUser->createToken('guest-token', ['guest:basic'])->plainTextToken;


        return response()->json([
    'access_token' => $token,
    'token_type'   => 'Bearer',
    'expires_in'   => 86400,
    'guest' => [
        'message'     => 'Guest registered',
        'guest_uuid'  => $room->guest_uuid,
        'guest_name'  => $room->guest_name,
        'room_number' => $room->room_number
    ]
],200);


    }
}

}
