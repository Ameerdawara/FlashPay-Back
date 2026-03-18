<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// استخدام ShouldBroadcastNow يضمن إرسال الرسالة فوراً دون انتظار طوابير العمل
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    // تحديد القناة (Channel) التي سنبث عليها، هنا القناة خاصة برقم الحوالة
    public function broadcastOn()
    {
        return new PrivateChannel('transfer.' . $this->message->transfer_id);
    }

    // تحديد البيانات التي سيستلمها تطبيق فلاتر
    public function broadcastWith()
    {
        return [
            'message' => $this->message->load('sender:id,name')
        ];
    }
}