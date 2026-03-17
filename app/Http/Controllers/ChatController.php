<?php

namespace App\Http\Controllers;

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
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        $transfer = Transfer::findOrFail($transferId);

        // تحديد من هو المستقبل (إذا كان المرسل هو الزبون، فالمستقبل هو الوكيل، والعكس)
        if ($user->id === $transfer->sender_id) {
            $receiverId = $transfer->destination_agent_id;
        } elseif ($user->id === $transfer->destination_agent_id) {
            $receiverId = $transfer->sender_id;
        } else {
            return response()->json(['message' => 'غير مصرح لك بإرسال رسالة هنا'], 403);
        }

        try {
            DB::beginTransaction();

            // 1. حفظ رسالة المستخدم
            $message = Message::create([
                'transfer_id' => $transferId,
                'sender_id'   => $user->id,
                'receiver_id' => $receiverId,
                'message'     => $request->message,
            ]);

            // التحضير لإرجاع قائمة بالرسائل الجديدة
            $newMessages = [$message->load('sender:id,name')];

            // 🤖 2. منطق الرد التلقائي
            // نتحقق: إذا كانت هذه هي الرسالة الأولى في الحوالة، والمرسل هو الزبون
            $isFirstMessage = Message::where('transfer_id', $transferId)->count() === 1;

            if ($isFirstMessage && $user->id === $transfer->sender_id) {
                // إرسال رسالة تلقائية نيابة عن الوكيل
                $autoMessage = Message::create([
                    'transfer_id' => $transferId,
                    'sender_id'   => $transfer->destination_agent_id, // الوكيل هو المرسل الآن
                    'receiver_id' => $transfer->sender_id, // الزبون هو المستقبل
                    'message'     => "مرحباً بك! لقد استلمت طلبك للحوالة رقم ({$transfer->tracking_code}). يرجى تحديد وقت ومكان اللقاء المناسب لك لتسليم المبلغ.",
                ]);
                $newMessages[] = $autoMessage->load('sender:id,name');
            }

            DB::commit();

            // سيتم تفعيل إرسال الحدث (Event) لاحقاً للـ Real-time
            // foreach ($newMessages as $msg) {
            //     broadcast(new MessageSent($msg))->toOthers();
            // }

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إرسال الرسالة بنجاح',
                'data'    => $newMessages // نرجع الرسالة (والرسالة التلقائية إن وجدت)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'فشل إرسال الرسالة: ' . $e->getMessage()
            ], 500);
        }
    }
}