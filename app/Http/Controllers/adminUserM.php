<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Order;
use Carbon\Carbon;


class adminUserM extends Controller
{

    public function sales(Request $request)
    {

        try {
            $business = $request->get('current_business');
            if (!$business) {
                return response()->json([
                    'error' => 'business_context_required',
                    'message' => 'Business context is required for order creation'
                ], 400);
            }

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


            $from = $request->query('from')
                ? Carbon::parse($request->query('from'))->startOfDay()
                : Carbon::now()->startOfDay();

            $to = $request->query('to')
                ? Carbon::parse($request->query('to'))->endOfDay()
                : Carbon::now()->endOfDay();



            $orders = Order::where('business_uuid', $business->uuid)
                ->where('current_status', 'delivered')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at', 'desc')
                ->get();

            //counters
            $totalSales = 0;
            $totalSalesCents = 0;
            $products = [];
            foreach ($orders as $order) {
                // Total sales
                $totalSales += $order->solicitud['total'] ?? 0;
                $totalSalesCents += $order->solicitud['total_cents'] ?? 0;

                // Product breakdown
                foreach ($order->solicitud['items'] as $item) {
                    $name = $item['name'];

                    if (!isset($products[$name])) {
                        $products[$name] = [
                            'name' => $name,
                            'qty' => 0,
                            'total' => 0,
                            'total_cents' => 0,
                        ];
                    }

                    $products[$name]['qty'] += $item['qty'];
                    $products[$name]['total'] += $item['line_total'];
                    $products[$name]['total_cents'] += $item['line_total_cents'];
                }
            }

            return response()->json([
                'meta' => [
                    'from' => $from->toDateTimeString(),
                    'to' => $to->toDateTimeString(),
                    'orders_count' => $orders->count(),
                    'currency' => 'MXN',
                ],
                'totals' => [
                    'sales' => $totalSales,
                    'sales_cents' => $totalSalesCents,
                ],
                'products' => array_values($products),
            ]);
        } catch (\Exception $e) {
            Log::error("Error in sales adminUserM", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'business_uuid' => $business->uuid ?? null
            ]);

            return response()->json([
                'error' => 'internal_server_error',
                'message' => 'An unexpected error occurred while reading sales for this business'
            ], 500);
        }
    }
}
