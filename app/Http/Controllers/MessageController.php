<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // 1. إرسال رسالة
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message_text'    => 'required|string',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        // إنشاء الرسالة
        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id'       => Auth::id(),
            'message_text'    => $validated['message_text'],
        ]);

        // إذا كان المرسل موظف ولم يكن هناك agent_id، نجعله هو الـ agent
        if (Auth::user()->role !== 'customer' && !$conversation->agent_id) {
            $conversation->update(['agent_id' => Auth::id()]);
        }

        // تحديث وقت المحادثة لتظهر في الأعلى
        $conversation->touch();

        return response()->json($message);
    }


    public function markAsRead($conversationId)
    {
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', Auth::id())
            ->update(['is_read' => true]);

        return response()->json(['status' => 'success']);
    }
}
