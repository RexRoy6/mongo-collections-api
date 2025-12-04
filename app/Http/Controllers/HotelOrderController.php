<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\createSolicitud;
use Illuminate\Support\Facades\Log;

class HotelOrderController extends Controller
{
    public function create(Request $request)
    {

        try{
             // Validate hotel order request
        $validated = $request->validate([
            'guest_uuid' => 'required|uuid',
            'solicitud'  => 'required|array'
        ]);

        // Validate guest
        $guest = User::where('guest_uuid', $validated['guest_uuid'])
                     ->where('role', 'client')
                     ->where('is_occupied', true)
                     ->first();

        if (!$guest) {
            return response()->json(['message' => 'Guest session not found'], 404);
        }

        /**
         * We now "inject" the request fields expected by createSolicitud
         */
        //dd($validated['solicitud']['note']);
        $mergedRequest = new Request([
            'channel'     => 'hotel-app',         // or dynamic
            'created_by'  => $guest->guest_uuid,
            'collection'  => 'orders',
            'solicitud'   => $validated['solicitud'],
            'notes'       => $validated['solicitud']['note']
        ]);


        }catch (\Exception $e) {

            Log::error("Error creating solicitud hotel order", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        /**
         * Reuse your generic createSolicitud controller.
         */
        $controller = new createSolicitud();
        return $controller->store($mergedRequest);
    }
}
