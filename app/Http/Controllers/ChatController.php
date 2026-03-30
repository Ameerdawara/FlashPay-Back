<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function getMessages($transferId)
    {
        $user = Auth::user();
        $transfer = Transfer::findOrFail($transferId);

        // الصلاحية: المرسل (الزبون) أو أي موظف في المكتب
        $isStaff = in_array($user->role, ['admin']);

        if ($transfer->sender_id !== $user->id && !$isStaff) {
            return response()->json(['message' => 'غير مصرح لك بمشاهدة هذه المحادثة'], 403);
        }

        $messages = Message::where('transfer_id', $transferId)
            ->with('sender:id,name,role')
            ->orderBy('created_at', 'asc')
            ->get();

        // تحديد الرسائل كمقروءة حسب الطرف الذي يفتح المحادثة
        if (!$isStaff) {
            // الزبون يقرأ رسائل الموظفين
            Message::where('transfer_id', $transferId)->where('receiver_id', $user->id)
                ->where('is_read', false)->update(['is_read' => true]);
        } else {
            // الموظفون يقرؤون رسائل الزبون
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

        // جلب معرف مسؤول ليكون بمثابة "النظام" لاستقبال رسائل الزبون
        $adminUser = User::whereIn('role', ['admin', 'super_admin'])->first();
        $systemId = $adminUser ? $adminUser->id : 1;

        // من هو المستقبل؟
        $receiverId = ($user->id === $transfer->sender_id) ? $systemId : $transfer->sender_id;

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

            // الرد التلقائي من المكتب
            $isFirstMessage = Message::where('transfer_id', $transferId)->count() === 1;

            if ($isFirstMessage && $user->id === $transfer->sender_id) {
                $autoMessage = Message::create([
                    'transfer_id' => $transferId,
                    'sender_id'   => $systemId, // النظام يرد باسم الإدمن
                    'receiver_id' => $transfer->sender_id,
                    'message'     => "مرحباً بك! طلبك للحوالة رقم ({$transfer->tracking_code}) وصل لمكتبنا. سنقوم بتجهيز المبلغ، ويمكنك التواصل معنا هنا لأي استفسار.",
                ]);
                $newMessages[] = $autoMessage->load('sender:id,name');
            }

            DB::commit();

            foreach ($newMessages as $msg) {
                broadcast(new MessageSent($msg));
            }
            // ✅ إضافة: إرسال إشعار للزبون إذا كان المرسل من طاقم المكتب
            if ($isStaff) {
                $customer = User::find($transfer->sender_id);
                if ($customer && $customer->fcm_token) {
                    $notificationBody = $request->message ? $request->message : 'تم إرسال صورة';

                    $fcmService = new \App\Services\FcmService();

                    // ✅ نمرر البيانات هنا كمصفوفة في المعامل الرابع
                    $fcmService->sendNotification(
                        $customer->fcm_token,
                        "رسالة جديدة بخصوص الحوالة ({$transfer->tracking_code})",
                        $notificationBody,
                        [
                            'transfer_id' => (string)$transfer->id, // تحويل الرقم لنص ضروري جداً لـ Firebase
                            'tracking_code'   => $transfer->tracking_code, // ✅ تمت الإضافة
                            'current_user_id' => (string)$customer->id,    // ✅ تمت الإضافة
                            'type'        => 'chat',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ]
                    );
                }
            }

            return response()->json(['status' => 'success', 'message' => 'تم الإرسال', 'data' => $newMessages], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'فشل الإرسال: ' . $e->getMessage()], 500);
        }
    }
}
