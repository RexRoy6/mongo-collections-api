<?php

namespace App\Models\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait HasStatusHistory
{
    public function addStatus(string $status, string $updatedBy, string $notes = '')
    {
        $history = $this->status_history ?? [];

        $entry = [
            'status'     => $status,
            'updated_at' => Carbon::now(),
            'updated_by' => $updatedBy,
            'notes'      => $notes
        ];

        $history[] = $entry;

        $this->status_history = $history;
        $this->current_status = $status;

        return $this->save();
    }

    public function validateStatus(array $data, array $allowedStatuses)
    {
        $validator = Validator::make($data, [
            'status'     => 'required|string|in:' . implode(',', $allowedStatuses),
            'updated_by' => 'required|string',
            'notes'      => 'nullable|string'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $data;
    }
}
