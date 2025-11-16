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
         dd('ola');

        try {
            $request->validate([
                'collection' => 'required|string|max:255',
                'uuid' => 'nullable|uuid',
                'channel' => 'nullable|string',
                'created_at' => 'nullable|string',
                'current_status' => 'nullable|string',
            ]);
            //dd($request->all());
            dd('ola');
            // Find the document

            $query = SolicitudesMedicamento::query();

            if (!isset($request->collection)) {
                throw new \InvalidArgumentException('Collection name is required');
            } 

            $record = new SolicitudesMedicamento;

            if ($request->uuid) {
                $uuidsArray = [$request->uuid];
                $document = $record->findByUuid($uuidsArray, $request->collection);
            } else {
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

                $document = $query->get();
                foreach ($document as $doc) {
                    $uuidsArray[] = $doc->uuid;
                }

                $document = $record->findByUuid($uuidsArray, $request->collection);
            }

            if ($document) {
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
