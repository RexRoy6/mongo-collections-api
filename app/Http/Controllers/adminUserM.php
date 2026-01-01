<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Order;

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
            $orders = Order::where('business_uuid', $business->uuid)
                    ->orderBy('created_at', 'desc')
                    ->get();
//aqui quiero filtrar las ordenes por parametro de rango de tiempo $time
//filtrar solo las que estan como ready nadamas
// me regresara :
//ventas en  monto total segun el parametro $time
//desglose de productos vendidos totales y su total segun el mismo $time

             dd( $orders->first());




            
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
