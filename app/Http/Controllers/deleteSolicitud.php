<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudesMedicamento;
use Illuminate\Validation\Rules\Exists;

class deleteSolicitud extends Controller
{
    public function destroy(Request $request)
    {

        try {

            $request->validate([
            'uuid' => 'required|uuid',
            'collection' => 'required|string|max:255'
        ]);
 
            $model = new SolicitudesMedicamento();
            $deleted = $model->deleteDocument($request->uuid, $request->collection);

            if ($deleted) {
                return response()->json([
                    'message' => 'Record deleted successfully',
                    'uuid' => $request->uuid
                ], 200);
            }

            return response()->json([
                'message' => 'Record not found',
                'uuid' => $request->uuid
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting record',
                'error' => $e->getMessage(),
                'uuid' => $request->uuid
            ], 500);
        }
    }



    public function cancelSolicitud(Request $request)
    {
        try {
            $uuid = $request->validate([
                'uuid' => 'required|uuid'
            ]);
            $statusData = $request->validate([
                //'status' => 'required|string|in:cancelled',
                'updated_by' => 'required|string',
                'notes' => 'nullable|string'
            ]);
            //dd($uuid['uuid'],$statusData);



            $collection = $request->validate([
                'collection' => 'required|string|max:255'
            ]);


            $model = new SolicitudesMedicamento();
            $updatedDocument = $model->cancelledStatus($uuid['uuid'], $statusData, $collection['collection']);

            return response()->json([
                'message' => 'Status updated successfully',
                'document' => $updatedDocument
            ], 200);



        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()

            ], 500);
        }
    }

}
