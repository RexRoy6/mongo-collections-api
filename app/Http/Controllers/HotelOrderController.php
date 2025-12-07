<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\createSolicitud;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Menu;

class HotelOrderController extends Controller
{
    public function create(Request $request)
    {

        try{

            $guest = $this->validateGuestUser($request);
    
            if (!$guest) return response()->json(['message' => 'Unauthorized'], 403);


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


      private function validateKitchenUser(Request $request)
{
    $uuid = $request->header('kitchenUser_uuid');

    if (!$uuid) return null;

    return User::where('role','kitchen')
               ->where('kitchenUser_uuid',$uuid)
               ->where('is_active',true)
               ->first();
}


      private function validateGuestUser(Request $request)
{
    $uuid = $request->header('guest_uuid');

    if (!$uuid) return null;

    return User::where('role','client')
               ->where('guest_uuid',$uuid)
               ->where('is_occupied',true)
               ->first();
}



    public function listOrders(Request $request)
{
    $staff = $this->validateKitchenUser($request);

    if (!$staff) return response()->json(['message' => 'Unauthorized'], 403);

    $orders = Order::whereIn('current_status', [
        'created','pending','preparing','ready'
    ])->get();

    return response()->json($orders, 200);
}

public function updateOrderStatus(Request $request)
{
    $staff = $this->validateKitchenUser($request);

    if (!$staff) return response()->json(['message'=>'Unauthorized'],403);
    $validated = $request->validate([
        'status' => 'required|string|in:pending,preparing,ready,delivered,cancelled'
    ]);

    $order = Order::where('uuid', $request->uuid)->first();

    if (!$order) return response()->json(['message'=>'Order not found'],404);

    // enforce workflow
    if (!$order->canTransition('kitchen', $validated['status'])) {
        return response()->json([
            'message' => 'Invalid status transition',
            'from'    => $order->current_status,
            'to'      => $validated['status']
        ], 422);
    }

    $order->updateStatus('kitchen', $validated['status'],'');//el ultimo paramn es para las notas de la cocina a la orden

    return response()->json([
        'message' => 'Order updated',
        'order'   => $order
    ], 200);
}

public function cancel(Request $request)
{

    //ponerle validacion de uuid o el barer token
    $clientUuid = $request->header('guest_uuid');

    if (!$clientUuid) return response()->json(['message'=>'Unauthorized'],403);

    $order = Order::where('uuid',$request->uuid)
                  ->where('created_by',$clientUuid)
                  ->first();

    if (!$order) return response()->json(['message'=>'Order not found'],404);

    if (!$order->canTransition('client','cancelled')) {
        return response()->json(['message'=>'Order cannot be cancelled'],422);
    }

    $order->updateStatus('client','cancelled',$request['notes']);

    return response()->json(['message'=>'Order cancelled'],200);
}

public function read(Request $request)
{

      try{

        $guest = $this->validateGuestUser($request);
    
        if (!$guest) return response()->json(['message' => 'Unauthorized'], 403);


        // Validate hotel order request
        $validated = $request->validate([
            'guest_uuid' => 'required|uuid'
        ]);

        
         $order = Order::where('created_by',$validated['guest_uuid'])
                  ->get();
     

        if (!$order) return response()->json(['message'=>'Order not found'],404);


        }catch (\Exception $e) {

            Log::error("Error reading solictud hotel order", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return response()->json($order,200);

}

}
