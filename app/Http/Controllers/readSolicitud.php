<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudesMedicamento;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Support\Carbon;

class readSolicitud extends Controller
{
    public function read(Request $request)
    {
        try {
            $request->validate([
                'collection' => 'required|string|max:255',
                'uuid' => 'nullable|uuid',
                'channel' => 'nullable|string',
                'created_at' => 'nullable|string',
                'current_status' => 'nullable|string',
            ]);

            $collection = $request->collection;
            
            // Create model instance with the specific collection
            $record = new SolicitudesMedicamento;
            $record->setCollection($collection); // Set the collection to query

            if ($request->uuid) {
                $uuidsArray = [$request->uuid];
                $document = $record->findByUuid($uuidsArray, $collection);
            } else {
                // Build query using the specific collection
                $query = $record->newQuery();
                
                if ($request->channel) {
                    $query->where('channel', $request->channel);
                }

                if ($request->created_at) {
                    $timestamp = Carbon::parse($request->created_at);
                    $query->whereDate('created_at', $timestamp);
                }

                if ($request->current_status) {
                    $query->where('current_status', $request->current_status);
                }

                if (!$request->channel && !$request->created_at && !$request->current_status && !$request->uuid) {
                    return response()->json([
                        'message' => 'Ningún criterio de búsqueda proporcionado',
                    ], 400);
                }

                $documents = $query->get();
                
                if ($documents->isEmpty()) {
                    return response()->json([
                        'message' => 'Record not found',
                    ], 404);
                }

                $uuidsArray = $documents->pluck('uuid')->toArray();
                $document = $record->findByUuid($uuidsArray, $collection);
            }

            if ($document && !empty($document)) {
                return response()->json($document, 200);
            }

            return response()->json([
                'message' => 'Record not found',
                'uuid' => $request->uuid
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}