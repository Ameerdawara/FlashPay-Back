<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function getMessages($transferId)
    {
        $user = Auth::user();
        $transfer = Transfer::findOrFail($transferId);

        // الصلاحية: المرسل (الزبون) أو أي موظف في المكتب
        // ✅ كانت تقبل 'admin' فقط، وأصبحت متسقة مع sendMessage
        $isStaff = in_array($user->role, ['admin', 'super_admin', 'cashier', 'accountant']);

        if ($transfer->sender_id !== $user->id && !$isStaff) {
            return response()->json(['message' => 'غير مصرح لك بمشاهدة هذه المحادثة'], 403);
        }

        // ✅ نحمّل role أيضاً ليعمل isStaff في تطبيق Flutter
        $messages = Message::where('transfer_id', $transferId)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        // تحديد الرسائل كمقروءة حسب الطرف الذي يفتح المحادثة
        if (!$isStaff) {
            Message::where('transfer_id', $transferId)->where('receiver_id', $user->id)
                ->where('is_read', false)->update(['is_read' => true]);
        } else {
            Message::where('transfer_id', $transferId)->where('sender_id', $transfer->sender_id)
                ->where('is_read', false)->update(['is_read' => true]);
        }

        return response()->json(['status' => 'success', 'data' => $messages], 200);
    }

    public function sendMessage(Request $request, $transferId)
    {
        $request->validate([
            'message' => 'nullable|string',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);
        if (!$request->message && !$request->hasFile('image')) {
            return response()->json(['message' => 'يجب إرسال نص أو صورة'], 422);
        }

        $user = Auth::user();
        $transfer = Transfer::findOrFail($transferId);
        $isStaff = in_array($user->role, ['admin', 'super_admin', 'cashier', 'accountant']);

        if ($user->id !== $transfer->sender_id && !$isStaff) {
            return response()->json(['message' => 'غير مصرح لك بإرسال رسالة'], 403);
        }

        // مسؤول يمثّل "النظام" لاستقبال رسائل الزبون
        $adminUser = User::whereIn('role', ['admin', 'super_admin'])->first();
        $systemId = $adminUser ? $adminUser->id : 1;

        $receiverId = ($user->id === $transfer->sender_id) ? $systemId : $transfer->sender_id;

        $newMessages = [];

        // ───────────── 1) الحفظ في قاعدة البيانات فقط ─────────────
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

            $newMessages[] = $message->load('sender:id,name,role');

            // الرد التلقائي من المكتب
            $isFirstMessage = Message::where('transfer_id', $transferId)->count() === 1;

            if ($isFirstMessage && $user->id === $transfer->sender_id) {
                $autoMessage = Message::create([
                    'transfer_id' => $transferId,
                    'sender_id'   => $systemId,
                    'receiver_id' => $transfer->sender_id,
                    'message'     => "مرحباً بك! طلبك للحوالة رقم ({$transfer->tracking_code}) وصل لمكتبنا. سنقوم بتجهيز المبلغ، ويمكنك التواصل معنا هنا لأي استفسار.",
                ]);
                $newMessages[] = $autoMessage->load('sender:id,name,role');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Chat save failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'فشل الإرسال: ' . $e->getMessage()], 500);
        }

        // ───────────── 2) البث اللحظي (فشله لا يجب أن يفشل الطلب) ─────────────
        // السبب السابق للخطأ 500: Pusher رجّع 404 بعد حفظ الرسالة، فكان الـ catch يعيد 500
        foreach ($newMessages as $msg) {
            try {
                broadcast(new MessageSent($msg))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Chat broadcast failed: ' . $e->getMessage());
            }
        }

        // ───────────── 3) إشعار FCM للزبون ─────────────
        if ($isStaff) {
            try {
                $customer = User::find($transfer->sender_id);
                if ($customer && $customer->fcm_token) {
                    (new \App\Services\FcmService())->sendNotification(
                        $customer->fcm_token,
                        "رسالة جديدة بخصوص الحوالة ({$transfer->tracking_code})",
                        $request->message ?: 'تم إرسال صورة',
                        [
                            'transfer_id'     => (string) $transfer->id,
                            'tracking_code'   => (string) $transfer->tracking_code,
                            'current_user_id' => (string) $customer->id,
                            'type'            => 'chat',
                            'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Chat FCM failed: ' . $e->getMessage());
            }
        }

        return response()->json(['status' => 'success', 'message' => 'تم الإرسال', 'data' => $newMessages], 201);
    }
}