<?php

namespace App\Models;

use App\Models\BaseMongoModel;
use App\Models\Traits\HasStatusHistory;



class Order extends BaseMongoModel
{
    use HasStatusHistory;

    protected $collection = 'orders';

    protected $fillable = [
        'channel',
        'created_by',
        'solicitud',
        'current_status',
        'status_history',
    ];

    protected $attributes = [
        'current_status' => 'created',
        'status_history' => [],
        'solicitud' => []
    ];

    public static function rules()
    {
        return [
            'channel' => 'required|string',
            'created_by' => 'required|string',
            'solicitud' => 'array',
            'notes'=> 'nullable|string'
        ];
    }

    /** Example status flow for restaurant */
    public static function allowedStatuses()
    {
        return [
            'created',
            'pending',
            'preparing',
            'ready',
            'delivered',
            'cancelled'
        ];
    }

    public static function allowedTransitions()
{
    return [
        'client' => [
            'created'   => ['cancelled'],
        ],

        'kitchen' => [
            'created'   => ['pending', 'preparing', 'cancelled'],
            'pending'   => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready'     => ['delivered'],
        ]
    ];
}

public function canTransition(string $role, string $newStatus): bool
{
    $workflow = self::allowedTransitions();

    $current = $this->current_status;
   
    return in_array($newStatus, $workflow[$role][$current] ?? []);
}

public function updateStatus(string $role, string $newStatus, string $notes)
{
    if (!$this->canTransition($role, $newStatus)) {
        return false;
    }

    // Reuse existing trait logic (safe Mongo array handling)
    return $this->addStatus(
        status: $newStatus,
        updatedBy: $role,
        notes: $notes
    );
}



}
