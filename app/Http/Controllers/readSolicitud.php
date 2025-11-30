<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;              // or any model extending BaseMongoModel
use Illuminate\Support\Carbon;

class ReadSolicitud extends Controller
{
    public function read(Request $request)
    {
        try {
            $validated = $request->validate([
                'collection'      => 'required|string|max:255',
                'uuid'            => 'nullable|uuid',
                'channel'         => 'nullable|string',
                'created_at'      => 'nullable|date',
                'current_status'  => 'nullable|string',
            ]);

            // Instantiate generic Mongo model with dynamic collection
            $model = (new Order)->setCollection($validated['collection']);

            // -------------------------------------------------------
            // 1. GET BY UUID
            // -------------------------------------------------------
            if ($request->filled('uuid')) {
                $doc = $model->where('uuid', $request->uuid)->first();

                return $doc
                    ? response()->json($doc, 200)
                    : response()->json(['message' => 'Record not found'], 404);
            }

            // -------------------------------------------------------
            // 2. BUILD QUERY DYNAMICALLY
            // -------------------------------------------------------
            $query = $model->newQuery();

            if ($request->filled('channel')) {
                $query->where('channel', $request->channel);
            }

            if ($request->filled('created_at')) {
                $query->whereDate('created_at', Carbon::parse($request->created_at));
            }

            if ($request->filled('current_status')) {
                $query->where('current_status', $request->current_status);
            }

            // If no filters provided
            if ($query->count() === 0 && !$request->only(['channel', 'created_at', 'current_status'])) {
                return response()->json([
                    'message' => 'No query filters provided'
                ], 400);
            }

            $documents = $query->get();

            if ($documents->isEmpty()) {
                return response()->json([
                    'message' => 'No records found'
                ], 404);
            }

            return response()->json($documents, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
