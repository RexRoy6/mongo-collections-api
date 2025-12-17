<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;
use App\Models\Business;

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

        try {
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

    public function create_user(Request $request)
    {
        try {
            $validated = $request->validate([
                'business_uuid' => 'required|string',
                'users' => 'required|array|min:1',
                'users.*.role' => 'required|string|in:client,kitchen,barista,admin',
            ]);


            $business = Business::where('uuid', $validated['business_uuid'])
                ->active()
                ->first();

            if (!$business) {
                return response()->json([
                    'error' => 'business_not_found'
                ], 404);
            }

            $allowedRoles = $business->public_config['login_options'] ?? [
                'client',
                'kitchen',
                'barista',
                'admin'
            ];

            $created = [];
            $errors  = [];
            $users = $request->input('users');

            foreach ($users as $index => $data) {
                if (!in_array($data['role'], $allowedRoles)) {
                    $errors[] = [
                        'index' => $index,
                        'role' => $data['role'],
                        'message' => 'Role not allowed for this business'
                    ];
                    continue;
                }

                $attributes = [
                    'business_uuid' => $business->uuid,
                    'role' => $data['role'],
                ];

                /* ===========================
       CLIENT
    ============================ */
                if ($data['role'] === 'client') {
                    $request->validate([
                        "users.$index.room_number" => 'required|integer',
                        "users.$index.room_key"    => 'required|integer',
                    ]);

                    $attributes += [
                        'room_number' => $data['room_number'],
                        'room_key'    => $data['room_key'],
                        'is_occupied' => false,
                    ];
                }

                /* ===========================
       STAFF
    ============================ */
                if (in_array($data['role'], ['kitchen', 'barista', 'admin'])) {
                    $request->validate([
                        "users.$index.name"         => 'required|string|max:255',
                        "users.$index.staff_number" => 'required|integer',
                        "users.$index.staff_key"    => 'required|integer',
                    ]);

                    $attributes += [
                        'name'         => $data['name'],
                        'staff_number' => $data['staff_number'],
                        'staff_key'    => $data['staff_key'],
                        'is_active'    => false,
                    ];
                }

                $user = User::create($attributes);

                $created[] = [
                    'uuid' => $user->id,
                    'role' => $user->role,
                    'status' => 'created'
                ];
            }


            return response()->json([
                'message' => 'User creation completed',
                'business' => $business->getPublicInfo(),
                'users' => $created,
                'errors' => $errors ?: null
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'error' => 'validation_error',
                'errors' => $e->errors()
            ], 422);
        }
    }
}
