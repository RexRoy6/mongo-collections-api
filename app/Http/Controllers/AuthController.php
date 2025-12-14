<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Business;

class AuthController extends Controller
{
    /**
     * CLIENT: Login or Register (Hotel room flow)
     */
    public function clientLogin(Request $request)
    {
        $business = $request->get('current_business');

        if (!$business) {
            return response()->json([
                'error' => 'business_context_required'
            ], 400);
        }

        $data = $request->validate([
            'room_number' => 'required|integer',
            'room_key'    => 'required|integer',
            'guest_name'  => 'nullable|string',
        ]);

        $room = User::forCurrentBusiness()
            ->where('role', 'client')
            ->where('room_number', $data['room_number'])
            ->first();

        if (!$room || $room->room_key !== $data['room_key']) {
            return response()->json([
                'error' => 'invalid_room'
            ], 403);
        }

        if ($room->is_occupied) {
            return response()->json([
                'error' => 'room_already_occupied'
            ], 422);
        }

        if (empty($data['guest_name'])) {
            return response()->json([
                'error' => 'guest_name_required'
            ], 422);
        }

        $room->assignGuest($data['guest_name']);

        $token = $room->issueToken($business, 'client-token');

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'business'     => $business->getPublicInfo(),
            'user' => [
                'role'        => 'client',
                'guest_name'  => $room->guest_name,
                'room_number' => $room->room_number,
            ]
        ]);
    }

    /**
     * CLIENT: Reset Room
     */
    public function clientResetRoom(Request $request)
    {
        $business = $request->get('current_business');

        $data = $request->validate([
            'room_number' => 'required|int',
            'room_key'    => 'required|int',
        ]);

        $room = User::forCurrentBusiness()
            ->where('role', 'client')
            ->where('room_number', $data['room_number'])
            ->where('room_key', $data['room_key'])
            ->first();

        if (!$room || !$room->is_occupied) {
            return response()->json([
                'error' => 'room_not_occupied'
            ], 422);
        }

        $room->resetRoom();

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * KITCHEN / BARISTA / ADMIN LOGIN
     */
    public function staffLogin(Request $request)
    {
        $business = $request->get('current_business');

        $data = $request->validate([
            'user_number' => 'required|int',
            'user_key'    => 'required|int',
        ]);

        $user = User::forCurrentBusiness()
            ->whereIn('role', ['kitchen', 'barista', 'admin'])
            ->where('user_number', $data['user_number'])
            ->first();

        if (!$user || $user->user_key !== $data['user_key']) {
            return response()->json([
                'error' => 'invalid_credentials'
            ], 403);
        }

        $user->is_active = true;
        $user->save();

        $token = $user->issueToken($business, 'staff-token');

        return response()->json([
            'access_token' => $token,
            'user' => [
                'role' => $user->role,
                'name' => $user->user_name,
            ]
        ]);
    }

    /**
     * STAFF LOGOUT
     */
    public function staffLogout(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->is_active = false;
        $user->save();

        return response()->json([
            'success' => true
        ]);
    }
}
