<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
// ✅ الحل للمشكلة الأولى: استيراد كلاس الـ Log
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        // تأكد أن المسار في ملف .env يشير لمكان ملف الـ JSON الصحيح
        $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification($fcmToken, $title, $body, array $data = [])
{
    if (!$fcmToken) return false;

    try {
        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->toToken($fcmToken)
            ->withNotification($notification);

        // ✅ إضافة البيانات إذا كانت موجودة
        if (!empty($data)) {
            // ملاحظة: FCM يتطلب أن تكون جميع القيم داخل data هي "Strings"
            $message = $message->withData($data);
        }

        $this->messaging->send($message);
        return true;
    } catch (\Exception $e) {
        Log::error('FCM Send Error: ' . $e->getMessage());
        return false;
    }
}
}
