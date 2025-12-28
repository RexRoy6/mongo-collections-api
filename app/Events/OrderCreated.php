<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}

    /**
     * Where the event is broadcast
     */
    public function broadcastOn(): Channel
    {
        return new Channel('kitchen-orders');
    }

    /**
     * Event name on the frontend
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * Data sent to frontend
     */
    public function broadcastWith(): array
    {
        return [
            'order_uuid' => $this->order->uuid,
            'status' => $this->order->current_status,
            'business_uuid' => $this->order->business_uuid,
            'created_by' => $this->order->created_by,
            'created_at' => $this->order->created_at,
        ];
    }
}
