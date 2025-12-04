<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;

class updateSolicitud extends Controller
{
    public function update(Request $request)
    {
        try {

            // Validate inputs
            $validated = $request->validate([
                'collection' => 'required|string|max:255',
                'uuid'       => 'required|uuid',
                'status'     => 'required|string',
                'updated_by' => 'required|string',
                'notes'      => 'nullable|string'
            ]);

            $collection = $validated['collection'];
            $uuid       = $validated['uuid'];

            // Load dynamic collection
            $model = (new Order)->setCollection($collection);

            // Find order
            $order = $model->where('uuid', $uuid)->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Record not found',
                    'uuid'    => $uuid
                ], 404);
            }

            // Ensure proper collection
            $order->setCollection($collection);

            // Verify status
            $allowed = Order::allowedStatuses();

            if (!in_array($validated['status'], $allowed)) {
                return response()->json([
                    'message' => 'Invalid status',
                    'allowed' => $allowed,
                    'given'   => $validated['status']
                ], 422);
            }

            // Add new status entry
            $order->addStatus(
                $validated['status'],
                $validated['updated_by'],
                $validated['notes'] ?? ''
            );

            // --------------------------------------------------
            // AUTO RESET THE ROOM WHEN ORDER COMPLETES
            // --------------------------------------------------
            if (in_array($validated['status'], ['delivered', 'cancelled'])) {

                // "created_by" stores the ROOM/GUEST USER UUID
                $user = User::where('uuid', $order->created_by)
                            ->where('role', 'client')
                            ->first();

                if ($user) {
                    $user->resetRoom();   // <-- we will create this method next
                }
            }

            return response()->json($order, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
