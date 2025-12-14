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

public function create_user(Request $request)
{
    try {
        $validated = $request->validate([
            'users' => 'required|array|min:1',
            'users.*.user_name' => 'required|string|max:255',
            'users.*.user_number' => 'required|integer',
            'users.*.user_pw' => 'required|int',
            'users.*.role' => 'required|string|in:admin,barista,client,kitchen',
            'business_uuid' => 'required|string',
        ]);

        // First, check if business exists
        $business = Business::where('uuid', $validated['business_uuid'])
            ->active()
            ->first();
            

        if (!$business) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Business not found or inactive'
            ], 404);
        }

        // Check business login options
        $allowedRoles = $business->public_config['login_options'] ?? ['admin', 'barista', 'client', 'kitchen'];

        $createdUsers = [];
        $errors = [];

        foreach ($validated['users'] as $userData) {
            // Validate role against business configuration
            if (!in_array($userData['role'], $allowedRoles)) {
                $errors[] = [
                    'user_number' => $userData['user_number'],
                    'user_name' => $userData['user_name'],
                    'role' => $userData['role'],
                    'message' => "Role '{$userData['role']}' is not allowed for this business. Allowed roles: " . implode(', ', $allowedRoles)
                ];
                continue;
            }

            // Prepare base data for all users
            $userAttributes = [
                'business_uuid' => $validated['business_uuid'],
                'role' => $userData['role'],
            ];

            // Handle different user types based on role
            switch ($userData['role']) {
                case 'client':
                    // Client users need room_number and room_key
                    if (!isset($userData['room_number']) || !isset($userData['room_key'])) {
                        $errors[] = [
                            'user_number' => $userData['user_number'],
                            'user_name' => $userData['user_name'],
                            'role' => $userData['role'],
                            'message' => 'Client users require room_number and room_key'
                        ];
                        continue 2; // Skip to next user
                    }

                    // Check if room already exists
                    $exists = User::where('business_uuid', $validated['business_uuid'])
                        ->where('role', 'client')
                        ->where('room_number', $userData['room_number'])
                        ->first();

                    if ($exists) {
                        $createdUsers[] = [
                            'user_number' => $userData['user_number'],
                            'user_name' => $userData['user_name'],
                            'role' => $userData['role'],
                            'room_number' => $userData['room_number'],
                            'status' => 'already exists',
                            'uuid' => $exists->id ?? null
                        ];
                        continue 2; // Skip to next user
                    }

                    $userAttributes['room_number'] = $userData['room_number'];
                    $userAttributes['room_key'] = $userData['user_pw']; // Use user_pw as room_key for clients
                    $userAttributes['guest_name'] = $userData['user_name'];
                    $userAttributes['is_occupied'] = false;
                    break;

                case 'kitchen':
                    // Kitchen users
                    $exists = User::where('business_uuid', $validated['business_uuid'])
                        ->where('role', 'kitchen')
                        ->where('number_kitchenNumber', $userData['user_number'])
                        ->first();

                    if ($exists) {
                        $createdUsers[] = [
                            'user_number' => $userData['user_number'],
                            'user_name' => $userData['user_name'],
                            'role' => $userData['role'],
                            'status' => 'already exists',
                            'uuid' => $exists->id ?? null
                        ];
                        continue 2;
                    }

                    $userAttributes['name_kitchenUser'] = $userData['user_name'];
                    $userAttributes['number_kitchenNumber'] = $userData['user_number'];
                    $userAttributes['kitchenUser_key'] = $userData['user_pw'];
                    $userAttributes['is_active'] = false;
                    break;

                case 'admin':
                case 'barista':
                    // Admin/barista users - you might need to adjust your model for these
                    // Currently your model doesn't have fields for admin/barista
                    // For now, we'll store them similar to kitchen users
                    $exists = User::where('business_uuid', $validated['business_uuid'])
                        ->where('role', $userData['role'])
                        ->where('name_kitchenUser', $userData['user_name'])
                        ->first();

                    if ($exists) {
                        $createdUsers[] = [
                            'user_number' => $userData['user_number'],
                            'user_name' => $userData['user_name'],
                            'role' => $userData['role'],
                            'status' => 'already exists',
                            'uuid' => $exists->id ?? null
                        ];
                        continue 2;
                    }

                    // Use kitchen user fields for admin/barista temporarily
                    $userAttributes['name_kitchenUser'] = $userData['user_name'];
                    $userAttributes['number_kitchenNumber'] = $userData['user_number'];
                    $userAttributes['kitchenUser_key'] = $userData['user_pw'];
                    $userAttributes['is_active'] = true;
                    break;
            }

            try {
                $user = new User($userAttributes);
                $user->save();

                $createdUsers[] = [
                    'user_number' => $userData['user_number'],
                    'user_name' => $userData['user_name'],
                    'role' => $userData['role'],
                    'status' => 'created',
                    'uuid' => $user->id,
                    'room_number' => $user->room_number ?? null,
                    'kitchen_number' => $user->number_kitchenNumber ?? null
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'user_number' => $userData['user_number'],
                    'user_name' => $userData['user_name'],
                    'role' => $userData['role'],
                    'message' => 'Failed to create user: ' . $e->getMessage()
                ];
            }
        }

        $response = [
            'message' => 'User creation processed',
            'business' => [
                'uuid' => $business->uuid,
                'name' => $business->business_key,
                'allowed_roles' => $allowedRoles
            ],
            'users' => $createdUsers
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation error in create_user: ' . $e->getMessage());
        return response()->json([
            'error' => 'Bad Request',
            'message' => 'Missing or invalid parameters',
            'errors' => $e->errors()
        ], 400);
    } catch (\Exception $e) {
        Log::error('Error in create_user: ' . $e->getMessage());
        return response()->json([
            'error' => 'Server Error',
            'message' => 'An unexpected error occurred'
        ], 500);
    }
}

}
