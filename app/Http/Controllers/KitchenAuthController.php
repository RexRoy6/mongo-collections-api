<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class KitchenAuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'number_kitchenNumber' => 'required|int',
            'kitchenUser_key'      => 'required|int',
        ]);

        $staff = User::where('role', 'kitchen')
            ->where('number_kitchenNumber', $validated['number_kitchenNumber'])
            ->first();

        if (!$staff) {
            return response()->json(['message' => 'Kitchen user not found'], 404);
        }

        if ($staff->kitchenUser_key !== $validated['kitchenUser_key']) {
            return response()->json(['message' => 'Invalid key'], 403);
        }

        $staff->activateKitchenUser();
        //dd($staff);




        $authUser = \App\Models\kitchenAuthUser::updateOrCreate(
    ['kitchenUser_uuid' => $staff->kitchenUser_uuid],
    [
        'name_kitchenUser'  => $staff->name_kitchenUser
    ]
);

$token = $authUser->createToken('kitchen-token', ['kitchen:basic'])->plainTextToken;



        return response()->json([
    'access_token' => $token,
    'token_type'   => 'Bearer',
    'expires_in'   => 86400,
    'guest' => [
        'message'     => 'Kitchen user logged in',
        'kitchenUser_uuid'  => $staff->kitchenUser_uuid,
        'name_kitchenUser'  => $staff->name_kitchenUser
    ]
],200);

    }




    public function logout(Request $request)
    {
        $validated = $request->validate([
            'number_kitchenNumber' => 'required|int',
            'kitchenUser_key'      => 'required|int',
        ]);

        $staff = User::where('role', 'kitchen')
            ->where('number_kitchenNumber', $validated['number_kitchenNumber'])
            ->first();

        if (!$staff) {
            return response()->json(['message' => 'Kitchen user not found'], 404);
        }

        if ($staff->kitchenUser_key !== $validated['kitchenUser_key']) {
            return response()->json(['message' => 'Invalid key'], 403);
        }

        $staff->deactivateKitchenUser();

        return response()->json(['message' => 'Kitchen user logged out'], 200);
    }

}
