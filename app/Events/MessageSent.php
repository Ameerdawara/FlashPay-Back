<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('sender:id,name');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transfer.' . $this->message->transfer_id),
        ];
    }

    // ✅ مهم جداً: يحدد شكل البيانات المُرسلة
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toArray(),
        ];
    }

    // اسم الحدث كما يستمع له Flutter
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }
}
