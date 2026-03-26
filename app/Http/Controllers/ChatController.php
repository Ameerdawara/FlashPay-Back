<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// لا تنسَ استيراد الـ Event لاحقاً عندما نصل لمرحلة الـ WebSockets
// use App\Events\MessageSent;

class ChatController extends Controller
{
    /**
     * جلب جميع رسائل محادثة معينة مرتبطة بحوالة
     */
    public function getMessages($transferId)
    {
        $user = Auth::user();
        $transfer = Transfer::findOrFail($transferId);

        // التحقق من الصلاحيات: هل المستخدم هو مرسل الحوالة أم وكيل الاستلام؟
        if ($transfer->sender_id !== $user->id && $transfer->destination_agent_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح لك بمشاهدة هذه المحادثة'], 403);
        }

        // جلب الرسائل مرتبة من الأقدم للأحدث
        $messages = Message::where('transfer_id', $transferId)
            ->with('sender:id,name') // جلب اسم المرسل مع كل رسالة
            ->orderBy('created_at', 'asc')
            ->get();

        // (اختياري) تحديد الرسائل كمقروءة إذا كان المستخدم الحالي هو المستقبل
        Message::where('transfer_id', $transferId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ], 200);
    }

    /**
     * إرسال رسالة جديدة (مع ميزة الرد التلقائي)
     */
   public function sendMessage(Request $request, $transferId)
{
    $request->validate([
        'message' => 'nullable|string',
        'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
    ]);

    if (!$request->message && !$request->hasFile('image')) {
        return response()->json(['message' => 'يجب إرسال نص أو صورة'], 422);
    }

    $user     = Auth::user();
    $transfer = Transfer::findOrFail($transferId);

    // --- تحديد المستقبل ---
    if ($user->id === $transfer->sender_id) {
        $receiverId = $transfer->destination_agent_id;
    } elseif ($user->id === $transfer->destination_agent_id) {
        $receiverId = $transfer->sender_id;
    } else {
        return response()->json(['message' => 'غير مصرح لك بإرسال رسالة هنا'], 403);
    }

    // ✅ التحقق الجديد: هل الطرف الآخر موجود؟
    if (is_null($receiverId)) {
        return response()->json([
            'message' => 'لا يمكن إرسال الرسالة: لم يتم تعيين وكيل استلام لهذه الحوالة بعد.',
        ], 422);
    }

    try {
        DB::beginTransaction();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('chat_images', 'public');
        }

        $message = Message::create([
            'transfer_id' => $transferId,
            'sender_id'   => $user->id,
            'receiver_id' => $receiverId,
            'message'     => $request->message,
            'image'       => $imagePath,
        ]);

        $newMessages = [$message->load('sender:id,name')];

        // الرد التلقائي — فقط إذا كان destination_agent_id موجوداً
        $isFirstMessage = Message::where('transfer_id', $transferId)->count() === 1;

        if ($isFirstMessage && $user->id === $transfer->sender_id && !is_null($transfer->destination_agent_id)) {
            $autoMessage = Message::create([
                'transfer_id' => $transferId,
                'sender_id'   => $transfer->destination_agent_id,
                'receiver_id' => $transfer->sender_id,
                'message'     => "مرحباً بك! لقد استلمت طلبك للحوالة رقم ({$transfer->tracking_code}). يرجى تحديد وقت ومكان اللقاء المناسب لك لتسليم المبلغ.",
            ]);
            $newMessages[] = $autoMessage->load('sender:id,name');
        }

        DB::commit();

        foreach ($newMessages as $msg) {
            try {
                broadcast(new MessageSent($msg))->toOthers();
            } catch (\Exception $e) {
                // تجاهل أخطاء البث
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إرسال الرسالة بنجاح',
            'data'    => $newMessages,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
