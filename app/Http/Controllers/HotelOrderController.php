<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;
use App\Events\OrderCreated;
use App\Events\OrderCancelled;
use App\Events\OrderStatusUpdated;

class HotelOrderController extends Controller
{
    public function create(Request $request)
    {
        try {
            // 1) Get business context from middleware
            $business = $request->get('current_business');
            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required for order creation'
                ], 400);
            }

            // 2) Validate incoming payload
            $validated = $request->validate([
                // 'menu_key' => 'nullable|string',
                'solicitud' => 'required|array',
                'solicitud.items' => 'required|array|min:1',
                'solicitud.note' => 'nullable|string',
                'solicitud.name' => 'nullable|string|max:255',
                'solicitud.currency' => 'required|string|in:mxn,usd',
            ]);
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'error' => 'unauthenticated',
                    'message' => 'Authentication required'
                ], 401);
            }
            //if block para decidir mensaje en base a rol
            if ($user->role == 'client') {

                if ($user->is_occupied != true) {
                    return response()->json([
                        'error' => 'unauthorized',
                        'message' => 'user not active'
                    ], 401);
                }
            } else {
                if ($user->is_active != true) {
                    return response()->json([
                        'error' => 'unauthorized',
                        'message' => 'user not active'
                    ], 401);
                }
            }

            //hotel client guest
            //            "is_occupied" => true
            // "is_active" => false
            // "business_uuid" => "00b2c552-e9d0-4ed8-936e-7931d3713fd4"
            // "role" => "client"
            // "room_number" => 101
            // "room_key" => 1234
            // "uuid" => "04caac05-5418-4b72-96b9-f15a086e12f7"


            //barista
            //  "is_occupied" => false
            // "is_active" => true
            // "business_uuid" => "27e3d113-485f-4b2a-9cd4-7dc350a6a6e8"
            // "role" => "barista"
            // "name" => "Rodrigo Aguilera"
            // "staff_number" => 2
            // "staff_key" => 6567061339



            if ($user->business_uuid !== $business->uuid) {
                return response()->json([
                    'error' => 'cross_business_access',
                    'message' => 'User does not belong to this business'
                ], 403);
            }

            // 4) Determine menu_key (use provided or default)
            //$menuKey = $validated['menu_key'] ?? 'menu_cafe'; //aqui cambiarloo, reoq eue ese menu no existe ya
            // 5) Load menu WITHIN THIS BUSINESS
            $menu = Menu::where('business_uuid', $business->uuid)
                ->first();

            $menuKey  = $menu->menu_key;



            if (!$menu) {
                return response()->json([
                    'error' => 'menu_not_found',
                    'message' => 'Menu not found in this business',
                    'menu_key' => $menuKey,
                    'business' => $business->getPublicInfo()
                ], 404);
            }

            // 6) Build lookup map (name -> item)
            $lookup = [];
            foreach ($menu->items as $mi) {
                $lookup[strtolower($mi['name'])] = $mi;
            }

            // 7) Validate each ordered item and compute totals
            $orderItems = [];
            $totalCents = 0;

            foreach ($validated['solicitud']['items'] as $idx => $it) {
                if (!isset($it['name'])) {
                    return response()->json([
                        'error' => 'item_name_required',
                        'message' => "Item at index $idx missing name"
                    ], 422);
                }

                $nameKey = strtolower($it['name']);
                if (!isset($lookup[$nameKey])) {
                    return response()->json([
                        'error' => 'item_not_in_menu',
                        'message' => "Item not found in menu",
                        'item' => $it['name'],
                        'menu_key' => $menuKey,
                        'available_items' => array_column($menu->items, 'name')
                    ], 422);
                }

                $menuItem = $lookup[$nameKey];

                // Validate quantity
                $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
                if ($qty < 1) {
                    return response()->json([
                        'error' => 'invalid_quantity',
                        'message' => "Invalid quantity for item {$it['name']}"
                    ], 422);
                }

                // Get price from menu (server authoritative)
                $menuPrice = $menuItem['price'];
                if (!is_numeric($menuPrice)) {
                    return response()->json([
                        'error' => 'invalid_menu_price',
                        'message' => "Invalid menu price for {$menuItem['name']}"
                    ], 500);
                }

                // Calculate in cents for precision
                $priceCents = (int) round($menuPrice * 100);
                $lineTotalCents = $priceCents * $qty;
                $totalCents += $lineTotalCents;

                // Build order item
                $orderItems[] = [
                    'name' => $menuItem['name'],
                    'qty'  => $qty,
                    'unit_price' => $menuPrice,
                    'unit_price_cents' => $priceCents,
                    'line_total' => $lineTotalCents / 100,
                    'line_total_cents' => $lineTotalCents,
                    'image' => $menuItem['image'] ?? null
                ];
            }

            // 8) Build final solicitud payload
            $finalSolicitud = $validated['solicitud'];
            $finalSolicitud['items'] = $orderItems;
            $finalSolicitud['menu_key'] = $menuKey;
            $finalSolicitud['total'] = $totalCents / 100;
            $finalSolicitud['total_cents'] = $totalCents;
            $finalSolicitud['currency'] = $validated['solicitud']['currency'];
            $finalSolicitud['guest_room'] = $user->room_number;

            // 9) Create order WITH BUSINESS CONTEXT
            $orderData = [
                'business_uuid' => $business->uuid,
                'channel' => 'hotel-app', //aqui cambairlo
                'created_by' => isset($user->guest_uuid) ? $user->guest_uuid : $user->staff_number, //aqui depende si lo hace hotel o barista
                'solicitud' => $finalSolicitud,
                'current_status' => 'created',
                'status_history' => [[
                    'status' => 'created',
                    'updated_at' => now()->toIso8601String(),
                    'updated_by' => $user->guest_uuid,
                    'notes' => $validated['solicitud']['note'] ?? 'Order placed'
                ]]
            ];
            //dd($orderData);

            $order = Order::create($orderData);
            //send to event:
            OrderCreated::dispatch($order);

            //test event
            //Log::info('OrderCreated dispatched', ['order_id' => $order->id]);
            //test event


            // 10) Return success response
            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order,
                'business' => $business->getPublicInfo(),
                'guest' => [
                    'name' => $user->name,
                    'room' => $user->room_number,
                    'uuid' => $user->guest_uuid
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Error creating hotel order", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'business_uuid' => $business->uuid ?? null,
                'guest_uuid' => $user->guest_uuid ?? null
            ]);

            return response()->json([
                'error' => 'internal_server_error',
                'message' => 'An unexpected error occurred while creating the order'
            ], 500);
        }
    }

    public function read(Request $request)
    {
        try {
            // Get business context
            $business = $request->get('current_business');

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }

            // Get authenticated user (guest)
            $user = $request->user();
            //dd($user);

            if (!$user) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'Authentication required'
                ], 401);
            }

            if ($user->is_active != true) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'user not active'
                ], 401);
            }

            // Get guest_uuid from token (assuming it's stored in token)
            if ($user->isClient()) {
                $orders = Order::where('business_uuid', $business->uuid)
                    ->where('created_by', $user->guest_uuid)
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                // staff/admin see all
                $orders = Order::where('business_uuid', $business->uuid)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }


            return response()->json([
                'orders' => $orders,
                'business' => $business->getPublicInfo(),
                'meta' => [
                    'total' => $orders->count(),
                    'role' => $user->role
                ]
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

    public function cancel(Request $request)
    {

        try {
            // Get business context
            $business = $request->get('current_business');
            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }

            $validated = $request->validate([
                'order_uuid' => 'required|uuid',
                'notes' => 'nullable|string'
            ]);
            // Get authenticated user (guest)
            $user = $request->user();
            // dd($user);

            if (!$user) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'Authentication required'
                ], 401);
            }
            if ($user->is_occupied != true) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'user not active/room not occupied'
                ], 401);
            }

            // Find order WITHIN THIS BUSINESS
            $order = Order::where('business_uuid', $business->uuid)
                ->where('uuid', $validated['order_uuid'])
                ->first();


            if (!$order) {
                return response()->json([
                    'error' => 'order_not_found',
                    'message' => 'Order not found in this business'
                ], 404);
            }

            // Check if order can be cancelled
            if (!$order->canTransition($user->role, 'cancelled')) {
                return response()->json([
                    'error' => 'invalid_status_transition',
                    'message' => 'Order cannot be cancelled in its current status',
                    'current_status' => $order->current_status
                ], 422);
            }

            // Update order status
            $success = $order->updateStatus(
                role: $user->role,
                newStatus: 'cancelled',
                notes: $validated['notes'] ?? 'Cancelled by guest'
            );

            if (!$success) {
                return response()->json([
                    'error' => 'status_update_failed',
                    'message' => 'Failed to update order status'
                ], 500);
            }
            //add event here
            OrderCancelled::dispatch($order);
            //test event
            //Log::info('OrderCancelled dispatched', ['order_id' => $order->id]);
            //test event

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'order' => $order,
                'business' => $business->getPublicInfo()
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

    public function listOrders(Request $request)
    {
        try {
            //aqui debo asegurarme que. suna ruta que role la pueden ver los de la cocina con lo tokens
            // Get business context
            $business = $request->get('current_business');
            $user = $request->user();

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }
            if ($user->is_active != true) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'staff not active'
                ], 401);
            }



            // Get orders for this business (today's orders)
            $orders = Order::where('business_uuid', $business->uuid)
                ->whereIn('current_status', [
                    'created',
                    'pending',
                    'preparing',
                    'ready',
                    'delivered',
                    'cancelled'
                ])
                ->whereDate('created_at', \Carbon\Carbon::today())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'orders' => $orders,
                'business' => $business->getPublicInfo(),
                'meta' => [
                    'total' => $orders->count(),
                    'date' => \Carbon\Carbon::today()->toDateString()
                ]
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

    public function updateOrderStatus(Request $request)
    {

        try {
            // Get business context
            $business = $request->get('current_business');
            $user = $request->user();

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }

            $validated = $request->validate([
                'order_uuid' => 'required|uuid',
                'status' => 'required|string|in:created,pending,preparing,ready,delivered,cancelled',
                'notes' => 'nullable|string'
            ]);
            //chocl only kitchen and baristas con do this step
            if ($user->is_active != true) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'user not active'
                ], 401);
            }

            // Find order WITHIN THIS BUSINESS
            $order = Order::where('business_uuid', $business->uuid)
                ->where('uuid', $validated['order_uuid'])
                ->first();

            if (!$order) {
                return response()->json([
                    'error' => 'order_not_found',
                    'message' => 'Order not found in this business'
                ], 404);
            }

            // Update order status (kitchen role)
            $success = $order->updateStatus(
                role: $user->role,
                newStatus: $validated['status'],
                notes: $validated['notes'] ?? "Status changed to {$validated['status']} $user->role"
            );

            if (!$success) {
                return response()->json([
                    'error' => 'invalid_status_transition',
                    'message' => 'Invalid status transition for this order',
                    'current_status' => $order->current_status,
                    'attempted_status' => $validated['status']
                ], 422);
            }
            //add update order event
            OrderStatusUpdated::dispatch($order);
            //test event
            //Log::info('OrderStatusUpdated dispatched', ['order_id' => $order->id]);
            //test event

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'order' => $order,
                'business' => $business->getPublicInfo()
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

    public function updateOrderItems(Request $request)
    {
        try {
            $business = $request->get('current_business');
            $user = $request->user();

            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required'
                ], 400);
            }

            if (!$user || !$user->is_active) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'User not active or unauthenticated'
                ], 401);
            }

            $validated = $request->validate([
                'order_uuid' => 'required|uuid',
                'items' => 'required|array|min:1',
                'items.*.name' => 'required|string',
                'items.*.qty' => 'nullable|integer|min:1',
                'note' => 'nullable|string',
            ]);

            // Find order in business
            $order = Order::where('business_uuid', $business->uuid)
                ->where('uuid', $validated['order_uuid'])
                ->first();

            if (!$order) {
                return response()->json([
                    'error' => 'order_not_found',
                    'message' => 'Order not found in this business'
                ], 404);
            }

            // Check allowed statuses
            $editableStatuses = ['created', 'pending', 'preparing', 'ready'];
            if (!in_array($order->current_status, $editableStatuses)) {
                return response()->json([
                    'error' => 'order_locked',
                    'message' => 'Order items can no longer be modified',
                    'current_status' => $order->current_status
                ], 422);
            }

            // Load menu
            $menu = Menu::where('business_uuid', $business->uuid)->first();
            if (!$menu) {
                return response()->json([
                    'error' => 'menu_not_found',
                    'message' => 'Menu not found'
                ], 404);
            }

            // Build menu lookup
            $lookup = [];
            foreach ($menu->items as $mi) {
                $lookup[strtolower($mi['name'])] = $mi;
            }

            // Rebuild items & totals
            $orderItems = [];
            $totalCents = 0;

            foreach ($validated['items'] as $idx => $item) {
                $key = strtolower($item['name']);

                if (!isset($lookup[$key])) {
                    return response()->json([
                        'error' => 'item_not_in_menu',
                        'item' => $item['name'],
                    ], 422);
                }

                $qty = isset($item['qty']) ? (int) $item['qty'] : 1;
                $menuItem = $lookup[$key];

                $priceCents = (int) round($menuItem['price'] * 100);
                $lineTotalCents = $priceCents * $qty;
                $totalCents += $lineTotalCents;

                $orderItems[] = [
                    'name' => $menuItem['name'],
                    'qty' => $qty,
                    'unit_price' => $menuItem['price'],
                    'unit_price_cents' => $priceCents,
                    'line_total' => $lineTotalCents / 100,
                    'line_total_cents' => $lineTotalCents,
                    'image' => $menuItem['image'] ?? null,
                ];
            }

            // Update solicitud
            $solicitud = $order->solicitud ?? [];

            $solicitud['items'] = $orderItems;
            $solicitud['total'] = $totalCents / 100;
            $solicitud['total_cents'] = $totalCents;

            $order->solicitud = $solicitud;
            $order->save();


            // Optional: add audit note to status history
            $order->addStatus(
                status: $order->current_status,
                updatedBy: $user->role,
                notes: $validated['note'] ?? 'Order items updated'
            );

            return response()->json([
                'success' => true,
                'message' => 'Order items updated successfully',
                'order' => $order
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'validation_error',
                'errors' => $e->errors()
            ], 422);
        }
    }
}
