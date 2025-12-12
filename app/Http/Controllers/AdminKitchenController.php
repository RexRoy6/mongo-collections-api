<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminKitchenController extends Controller
{
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'kitchenUsers'                   => 'required|array|min:1',
            'kitchenUsers.*.name_kitchenUser'   => 'required|string',
            'kitchenUsers.*.number_kitchenNumber' => 'required|int',
            'kitchenUsers.*.kitchenUser_key'      => 'required|int',
            'business_uuid' => 'required|string',
        ]);

        $created = [];

        foreach ($validated['kitchenUsers'] as $kUser) {

            // Prevent duplicate staff number
            $exists = User::where('business_uuid', $validated['business_uuid'])
                        ->where('role', 'kitchen')
                        ->where('number_kitchenNumber', $kUser['number_kitchenNumber'])
                        ->first();

            if ($exists) {
                $created[] = [
                    'number_kitchenNumber' => $kUser['number_kitchenNumber'],
                    'status' => 'already exists'
                ];
                continue;
            }

            $staff = new User([
                'business_uuid' => $validated['business_uuid'],
                'role' => 'kitchen',
                'name_kitchenUser'      => $kUser['name_kitchenUser'],
                'number_kitchenNumber'  => $kUser['number_kitchenNumber'],
                'kitchenUser_key'       => $kUser['kitchenUser_key'],
                'is_active'             => false,
                'kitchenUser_uuid'      => null
            ]);

            $staff->save();

            $created[] = [
                'number_kitchenNumber' => $staff->number_kitchenNumber,
                'status'               => 'created'
            ];
        }

        return response()->json([
            'message' => 'Kitchen staff creation processed',
            'data'    => $created
        ], 200);
    }
}
