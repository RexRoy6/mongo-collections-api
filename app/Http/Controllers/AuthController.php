<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Business;
use App\Helpers\BusinessHelper;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * CLIENT: Login or Register (Hotel room flow)
     */
    public function clientLogin(Request $request)
    {

        try {
            $business = $request->get('current_business');

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required'
                ], 400);
            }

            $data = $request->validate([
                'room_number' => 'required|integer',
                'room_key'    => 'required|integer',
                'guest_name'  => 'required|string',
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
                    'guest_name'  => $room->name,
                    'room_number' => $room->room_number,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'errors' => $e->getMessage()
            ], 500);
        }
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

        try {
            $business = $request->get('current_business');

            $data = $request->validate([
                'staff_number' => 'required|int',
                'staff_key'    => 'required|int',
            ]);

            $user = User::forCurrentBusiness()
                ->whereIn('role', ['kitchen', 'barista', 'admin'])
                ->where('staff_number', $data['staff_number'])
                ->first();

            if (!$user || $user->staff_key !== $data['staff_key']) {
                return response()->json([
                    'error' => 'invalid_credentials'
                ], 403);
            }

            $user->is_active = true;
            $user->save();

            $token = $user->issueToken($business, 'staff-token');
            //seria buena idea que regresara de que business pertenece
            return response()->json([
                'access_token' => $token,
                'user' => [
                    'role' => $user->role,
                    'name' => $user->name,
                ],
                'business' => $business->getPublicInfo()
            ]);
        } catch (\Exception $e) {
            Log::error('Error in log_inuser satff: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server Error',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * STAFF LOGOUT
     */
    public function staffLogout(Request $request)
    {

        try {
            // Get business from middleware
            $business = $request->get('current_business');

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }

            $validated = $request->validate([
                'staff_number' => 'required|int',
                'staff_key'      => 'required|int',
            ]);
            //dd($business , $validated);


            //enocntrar al staff

            $staff = User::forCurrentBusiness()
                ->whereIn('role', ['kitchen', 'barista', 'admin'])
                ->where('staff_number', $validated['staff_number'])
                ->first();



        if ($staff->is_active == false) {
            return response()->json([
                'error' => 'already_logged_out',
                'message' => 'staff user already logged out'
            ], 422);
        }


        if ($staff->staff_key !== $validated['staff_key']) {
            return response()->json([
                'error' => 'invalid_key',
                'message' => 'Invalid staff user key'
            ], 403);
        }
           if (!$staff) {
            return response()->json([
                'error' => 'staff_user_not_found',
                'message' => 'staff user not found in this business'
            ], 404);
        }

        #ahora si desactivarlo lnene
         $staff->deactivateStaff();

         return response()->json([
            'success' => true,
            'message' => 'staff user logged out successfully',
            'business' => $business->getPublicInfo(),
            'staff' => [
                'number_staff_Number' => $staff->staff_number,
                'name_staff' => $staff->name,
                'is_active' => $staff->is_active,
                'role' => $staff->role
            ]
        ], 200);


        } catch (\Exception $e) {
            Log::error('Error in log_inuser staff: ' . $e->getMessage());
            return response()->json([
                'error' => 'Server Error',
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
