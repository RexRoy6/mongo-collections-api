<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use App\Models\SolicitudesMedicamento;

class updateSolicitud extends Controller
{
    public function update(Request $request)
    {
            try {
        $uuid =  $request->validate([
            'uuid' => 'required|uuid'
        ]);
        $statusData = $request->validate([
            'updated_by' => 'required|string',
            'notes' => 'nullable|string',
            'status' =>'required|string',
            'collection' => 'required|string|max:255'
        ]);

        $model = new SolicitudesMedicamento();
        $updatedDocument = $model->updateStatus($uuid['uuid'], $statusData, $request->collection);

          if ($updatedDocument) {
            return response()->json($updatedDocument, 200);
        }

        return response()->json([
            'message' => 'Record not found',
            'uuid' => $request->uuid
        ], 404);

        }catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()

        ], 500);
    }

    }
}
