<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;

class deleteSolicitud extends Controller
{
    public function destroy(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'collection' => 'required|string|max:255',
                'uuid'       => 'required|uuid'
            ]);

            $collection = $validated['collection'];
            $uuid       = $validated['uuid'];

            // Switch collection & find document
            $model = (new Order)->setCollection($collection);

            $doc = $model->where('uuid', $uuid)->first();

            if (!$doc) {
                return response()->json([
                    'message' => 'Record not found',
                    'uuid'    => $uuid
                ], 404);
            }

            // Perform delete using static method
            $deleted = Order::deleteByUuid($uuid, $collection);

            if ($deleted) {
                return response()->json([
                    'message' => 'Record deleted successfully',
                    'uuid'    => $uuid
                ], 200);
            }

            return response()->json([
                'message' => 'Unable to delete record'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting record',
                'error'   => $e->getMessage(),
                'uuid'    => $request->uuid
            ], 500);
        }
    }

}
