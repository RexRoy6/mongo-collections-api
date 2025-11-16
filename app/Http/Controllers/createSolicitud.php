<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudesMedicamento;
use Validator;
use Illuminate\Validation\ValidationException;
class createSolicitud extends Controller
{
public function store(Request $request)
{
    try {
        $input = $request->validate([
            'channel' => 'required|string|in:pwa,web,app,postman,DocExtTest,DocExt',
            'created_by' => 'required|string|max:255',
            'solicitud' => 'nullable|array',
            'collection' => 'required|string|max:255'
        ]);

        $solicitud = SolicitudesMedicamento::createSolicitud($input);

        //dd($solicitud->created_at);
        //$solicitud->created_at->format('Y-m-d H:i:s'),

        return response()->json([
            'uuid' => $solicitud->uuid,
            'created_at' => $solicitud->created_at,
            'channel' => $solicitud->channel,
            'created_by' => $solicitud->created_by,
            'status_history' => $solicitud->status_history,
            'solicitud' => (object)$solicitud->solicitud
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}
}
