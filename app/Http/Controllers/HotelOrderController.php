<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\createSolicitud;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Menu;
use Illuminate\Support\Carbon;

class HotelOrderController extends Controller
{
    public function create(Request $request)
    {

        try{

            // 2) validate incoming payload
            $validated = $request->validate([
                'guest_uuid' => 'required|uuid',
                'solicitud' => 'required|array',
                'solicitud.items' => 'required|array|min:1',
                'solicitud.menu_key' => 'nullable|string',
                'solicitud.note' => 'nullable|string',
                'solicitud.currency' => 'required|string',
                // optionally accept client-sent currency, payment method, etc.
            ]);

            // 3) re-check guest is active (safety)
            $guest = User::where('guest_uuid', $validated['guest_uuid'])
                         ->where('role', 'client')
                         ->where('is_occupied', true)
                         ->first();

            if (!$guest) {
                return response()->json(['message' => 'Guest session not found'], 404);
            }

            // 4) determine menu_key (use provided or default)
            $menuKey = $validated['solicitud']['menu_key'] ?? 'menu_cafe';

            // 5) load menu
            $menu = Menu::where('menu_key', $menuKey)->first();
            if (!$menu) {
                return response()->json([
                    'message' => 'Menu not found',
                    'menu_key' => $menuKey
                ], 404);
            }

            // 6) build lookup map (name -> item)
            $lookup = [];
            foreach ($menu->items as $mi) {
                // normalize item name for lookup (case-insensitive)
                $lookup[strtolower($mi['name'])] = $mi;
            }

            // 7) validate each ordered item and compute totals (in cents)
            $orderItems = [];
            $totalCents = 0;
            foreach ($validated['solicitud']['items'] as $idx => $it) {
                // validate minimal fields
                if (!isset($it['name'])) {
                    return response()->json(['message' => "Item at index $idx missing name"], 422);
                }
                $nameKey = strtolower($it['name']);
                if (!isset($lookup[$nameKey])) {
                    return response()->json([
                        'message' => "Item not found in menu",
                        'item' => $it['name'],
                        'menu_key' => $menuKey
                    ], 422);
                }

                $menuItem = $lookup[$nameKey];

                // quantity
                $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
                if ($qty < 1) {
                    return response()->json(['message' => "Invalid qty for item {$it['name']}"], 422);
                }

                // price check: server is authority. compute priceCents from menu
                // (store prices as numbers in menu, assumed currency e.g. MXN)
                $menuPrice = $menuItem['price'];
                if (!is_numeric($menuPrice)) {
                    return response()->json(['message' => "Invalid menu price for {$menuItem['name']}"], 500);
                }

                // use cents (integer) math
                $priceCents = (int) round($menuPrice * 100);
                $lineTotalCents = $priceCents * $qty;
                $totalCents += $lineTotalCents;

                // build final order item (server authoritative price)
                $orderItems[] = [
                    'name' => $menuItem['name'],
                    'qty'  => $qty,
                    'unit_price' => $menuPrice,
                    'unit_price_cents' => $priceCents,
                    'line_total' => $lineTotalCents / 100, // float for output
                    'line_total_cents' => $lineTotalCents,
                    'image' => $menuItem['image'] ?? null
                ];
            }
            // 8) build final solicitud payload (adds total + items + menu_key + payment placeholders)
            $finalSolicitud = $validated['solicitud'];
            $finalSolicitud['items'] = $orderItems;
            $finalSolicitud['menu_key'] = $menuKey;
            $finalSolicitud['total'] = $totalCents / 100;
            $finalSolicitud['total_cents'] = $totalCents;
            $finalSolicitud['currency'] = $validated['solicitud']['currency'];
            $finalSolicitud['guest_room'] = $guest->room_number;
            // optionally add payment method placeholder:
            // $finalSolicitud['payment_method'] = $validated['solicitud']['payment_method']
           

        /**
         * We now "inject" the request fields expected by createSolicitud
         */
        //dd($validated['solicitud']['note']);
        $mergedRequest = new Request([
            'channel'     => 'hotel-app',         // or dynamic
            'created_by'  => $guest->guest_uuid,
            'collection'  => 'orders',
            'solicitud'   => $finalSolicitud,
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
 
$orders = Order::whereIn('current_status', [
    'created', 'pending', 'preparing', 'ready', 'delivered', 'cancelled'
])
->whereBetween('created_at', [
     Carbon::today()->startOfDay(),
    Carbon::today()->endOfDay()
  
])
->orderBy('created_at', 'desc')
->get();

    return response()->json($orders, 200);
}

public function updateOrderStatus(Request $request)
{

    $validated = $request->validate([
        'status' => 'required|string|in:pending,preparing,ready,delivered,cancelled',
        'notes' => 'nullable|string'
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

    $order->updateStatus('kitchen', $validated['status'],$validated['notes']);

    return response()->json([
        'message' => 'Order updated',
        'order'   => $order
    ], 200);
}

public function cancel(Request $request)
{

    $order = Order::where('uuid',$request->uuid)
                  ->where('created_by',$request->guest_uuid)
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
        // Validate hotel order request
        $validated = $request->validate([
            'guest_uuid' => 'required|uuid'
        ]);

        
         $order = Order::where('created_by',$validated['guest_uuid'])// aqui debemos ponerle el filtto para que solo aparezcan las del dia
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
