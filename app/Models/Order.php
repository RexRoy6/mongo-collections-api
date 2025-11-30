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
}
