<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Create one or multiple hotel rooms.
     * Accepts single object or array of rooms.
     */
    public function create(Request $request)
    {
      try {
        $validated = $request->validate([
            'rooms' => 'required|array|min:1',
            'rooms.*.room_number' => 'required|integer',
            'rooms.*.room_key'    => 'required|integer',
            'business_uuid' => 'required|string',
        ]);
    

        $createdRooms = [];

        foreach ($validated['rooms'] as $roomData) {

            // Check if room already exists
            $exists = User::where('business_uuid', $validated['business_uuid'])
                ->where('role', 'client')
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
                'business_uuid' => $validated['business_uuid'],
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
        } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error($e->getMessage());
        return response()->json([
            'error' => 'Bad Request',
            'message' => 'Missing or invalid parameters',
            'errors' => $e->errors()
        ], 400);
    }
    }

    public function createMenu(Request $request)
{

    try{
        $validated = $request->validate([
        'menu_key' => 'required|string',        // e.g., "menu_cafe"
        'menu_info' => 'nullable|string',
        'items' => 'required|array|min:1',
        'items.*.name'  => 'required|string',
        'items.*.price' => 'required|numeric|min:0',
        'items.*.image' => 'nullable|string',
        'business_uuid' => 'required|string',
    ]);

    // Check if this menu already exists (you might want to update instead)
    $existing = Menu::where('business_uuid', $validated['business_uuid'])
        ->where('menu_key', $validated['menu_key'])->first();

    if ($existing) {
        return response()->json([
            'message' => 'Menu already exists',
            'menu' => $existing
        ], 409);
    }

    $menu = new Menu();
    $menu->menu_key = $validated['menu_key'];
    $menu->menu_info = $validated['menu_info'] ?? '';
    $menu->items = $validated['items'];
    $menu->business_uuid =  $validated['business_uuid'];

    $menu->save();

    return response()->json([
        'message' => 'Menu created successfully',
        'menu' => $menu
    ], 200);


    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error($e->getMessage());
        return response()->json([
            'error' => 'Bad Request',
            'message' => 'Missing or invalid parameters',
            'errors' => $e->errors()
        ], 400);
    }
    
}

}
