<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class createSolicitud extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate using model rules + collection
            $input = $request->validate(array_merge(
                Order::rules(),
                ['collection' => 'required|string|max:255']
            ));

            // Create instance with correct collection
            $order = new Order();
            $order->setCollection($input['collection']);

            // Fill model fields
            $order->fill([
                'channel'     => $input['channel'],
                'created_by'  => $input['created_by'],
                'solicitud'   => $input['solicitud'] ?? [],
            ]);

            // Save base order (UUID + timestamps)
            $order->save();

            /**
             * ---------------------------------------------------------
             * ADD INITIAL STATUS USING THE TRAIT
             * ---------------------------------------------------------
             */
            $order->addStatus(
                status: 'created',
                updatedBy: $input['created_by'],
                notes: ''//aqui agregar las notas
            );

            return response()->json([
                'uuid'           => $order->uuid,
                'created_at'     => $order->created_at,
                'channel'        => $order->channel,
                'created_by'     => $order->created_by,
                'current_status' => $order->current_status,
                'status_history' => $order->status_history,
                'solicitud'      => (object) $order->solicitud,
            ], 200);

        } catch (\Exception $e) {

            Log::error("Error creating solicitud", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
