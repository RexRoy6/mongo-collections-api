<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

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

            // Create model instance with dynamic collection
            $model = (new Order)->setCollection($collection);

            // Locate the order
            $order = $model->where('uuid', $uuid)->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Record not found',
                    'uuid'    => $uuid
                ], 404);
            }

            // Ensure this model instance uses the correct collection when saving
            $order->setCollection($collection);

            // Validate status against allowed list (in Order model)
            $allowed = Order::allowedStatuses();

            if (!in_array($validated['status'], $allowed)) {
                return response()->json([
                    'message' => 'Invalid status',
                    'allowed' => $allowed,
                    'given'   => $validated['status']
                ], 422);
            }

            // Add the new status using the Trait
            $order->addStatus(
                $validated['status'],
                $validated['updated_by'],
                $validated['notes'] ?? ''
            );

            return response()->json($order, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
