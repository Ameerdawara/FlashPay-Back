<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\MainSafe;
use App\Models\Currency;
use App\Models\TransferHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Transfer::query();

       

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->with(['sender', 'currency', 'sendCurrency','destinationOffice'])->where('destination_office_id', $user->office_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transfers
        ], 200);
    }

    /**
     * إنشاء حوالة جديدة (مخصصة للمستخدم/الزبون)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'                 => 'required|numeric|min:1',
            'currency_id'            => 'required|exists:currencies,id',
            'send_currency_id'       => 'required|exists:currencies,id',
            'receiver_name'          => 'required|string|max:255',
            'receiver_phone'         => 'required|string|max:20',
            'destination_office_id'  => 'required_without:destination_country_id|nullable|exists:offices,id',
            'destination_country_id' => 'required_without:destination_office_id|nullable|exists:countries,id',
            'destination_city'       => 'required_with:destination_country_id|nullable|string',
        ]);

        $trackingCode = 'TRX-' . strtoupper(Str::random(8));

        $currency = Currency::with('rates')->findOrFail($validated['send_currency_id']);
        $amountInUsd = $validated['amount'] * $this->getEffectiveRate($currency, $validated['amount']);

        $transfer = Transfer::create([
            'tracking_code'          => $trackingCode,
            'sender_id'              => Auth::id(),
            'amount'                 => $validated['amount'],
            'amount_in_usd'          => $amountInUsd,
            'currency_id'            => $validated['currency_id'],
            'send_currency_id'       => $validated['send_currency_id'],
            'destination_office_id'  => $validated['destination_office_id'] ?? null,
            'destination_country_id' => $validated['destination_country_id'] ?? null,
            'destination_city'       => $validated['destination_city'] ?? null,
            'receiver_name'          => $validated['receiver_name'],
            'receiver_phone'         => $validated['receiver_phone'],
            'status'                 => 'waiting',
            'fee'                    => 0,
            'receiver_id_image'      => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transfer created successfully',
            'data' => $transfer
        ], 201);
    }

    /**
     * تعديل بيانات الحوالة (admin فقط)
     * ملاحظة: تسجيل السجل (History) يتم تلقائياً عبر TransferObserver
     */
    public function editTransfer(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك بتعديل الحوالة'], 403);
        }

        $validated = $request->validate([
            'receiver_name'  => 'sometimes|string|max:255',
            'receiver_phone' => 'sometimes|string|max:20',
            'amount'         => 'sometimes|numeric|min:1',
            'fee'            => 'sometimes|numeric|min:0',
            // الملاحظات لا تحفظ في جدول الحوالات مباشرة، يمكن استخدامها لاحقاً
            'notes'          => 'sometimes|nullable|string|max:500',
        ]);

        // تصفية الحقول لتجنب أخطاء قاعدة البيانات (استبعاد notes)
        $updateFields = array_filter($validated, fn($k) => $k !== 'notes', ARRAY_FILTER_USE_KEY);

        // إعادة حساب amount_in_usd إذا تغيّر المبلغ
        if (isset($updateFields['amount'])) {
            $currency = Currency::with('rates')->find($transfer->send_currency_id);
            if ($currency) {
                $updateFields['amount_in_usd'] = $updateFields['amount'] * $this->getEffectiveRate($currency, $updateFields['amount']);
            }
        }

        // التحديث فقط (الـ TransferObserver سيتكفل بإنشاء الـ TransferHistory)
        $transfer->update($updateFields);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تعديل الحوالة بنجاح',
            'data'    => $transfer->load(['sender', 'currency', 'sendCurrency'])
        ]);
    }

    /**
     * جلب سجل التعديلات لحوالة محددة أو كل السجلات
     */
    public function transferHistory(Request $request, $id = null)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك بعرض سجل التعديلات'], 403);
        }

        $query = TransferHistory::with(['transfer', 'admin'])->orderBy('created_at', 'desc');

        if ($id) {
            $query->where('transfer_id', $id);
        } else {
            $query->take(200);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->get()
        ]);
    }

    public function update(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user = Auth::user();

        // 1. التحقق من الصلاحيات والبيانات المرسلة حسب دور الموظف
        if (in_array($user->role, ['admin', 'super_admin'])) {
            $request->validate([
                'status' => 'required|in:ready',
                'fee'    => 'required|numeric|min:0'
            ]);
        } elseif (in_array($user->role, ['cashier', 'accountant'])) {
            $request->validate([
                'status'            => 'required|in:completed',
                'receiver_id_image' => 'required|image|mimes:jpeg,png,jpg|max:4096'
            ]);
        } else {
            return response()->json(['message' => 'ليس لديك صلاحية لتحديث الحوالة'], 403);
        }

        // 2. تطبيق التعديلات داخل Transaction
        return DB::transaction(function () use ($request, $transfer, $user) {

            // الإدمن يوافق على الحوالة الواردة ويجهزها للاستلام
            if (in_array($user->role, ['admin', 'super_admin'])) {
                if ($request->status === 'ready' && $transfer->status === 'waiting') {
                    // ✅ إضافة: إرسال إشعار للزبون بأن الحوالة جاهزة
                    $customer = \App\Models\User::find($transfer->sender_id);
                    if ($customer && $customer->fcm_token) {
                        $fcmService = new \App\Services\FcmService();
                        $fcmService->sendNotification(
                            $customer->fcm_token,
                            "حوالتك جاهزة! ✅",
                            "طلبك للحوالة رقم ({$transfer->tracking_code}) أصبح جاهزاً للاستلام.",
                            [
                                'transfer_id' => (string)$transfer->id,
                                'type'        => 'transfer_ready',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ]
                        );
                    }


                    // إرسال رسالة الواتساب
                    $phone = $transfer->receiver_phone;
                    $amount = $transfer->amount;
                    $currency = $transfer->currency->code ?? '';
                    $whatsappMessage = "مرحباً المستلم الكريم، نعلمك أن حوالتك رقم ({$transfer->tracking_code}) بقيمة $amount $currency أصبحت جاهزة للاستلام الآن من مكتبنا.";

                    try {
                        Http::post('رابط_الـ_API_الخاص_بمزود_الواتساب', [
                            'token' => 'YOUR_API_TOKEN',
                            'to'    => $phone,
                            'body'  => $whatsappMessage
                        ]);
                    } catch (\Exception $e) {
                        Log::error('فشل إرسال رسالة واتساب للحوالة ' . $transfer->id . ' السبب: ' . $e->getMessage());
                    }
                }


                $transfer->status = $request->status;
                $transfer->fee = $request->fee;
            }

            // الكاشير يسلم المبلغ وينهي الحوالة
          elseif (in_array($user->role, ['cashier', 'accountant'])) {
    if ($request->status === 'completed' && $transfer->status === 'ready') {
        // 1. تحديث رصيد الصندوق الرئيسي للمكتب
        $officeSafe = MainSafe::where('owner_id', $transfer->destination_office_id)
            ->where('owner_type', 'App\Models\Office')
            ->first();

        if (!$officeSafe) throw new \Exception("صندوق المكتب غير موجود");

        $officeSafe->decrement('balance', $transfer->amount_in_usd);

        // 2. حساب الربح بناءً على فرق سعر العملة
        $currency = \App\Models\Currency::find($transfer->send_currency_id);

        if ($currency) {
            // حساب الفرق بين سعر البيع (price) وسعر التكلفة (main_price)
            $priceDiff = (float)$currency->price - (float)$currency->main_price;
            $profit = $transfer->amount * $priceDiff;

            // تخزين الربح في حقل العمولات الخاص بالحوالة
            $transfer->fee = $profit;

            // 3. ترحيل الربح إلى جدول أرباح المكاتب (ProfitSafe)
            // نستخدم updateOrCreate لضمان وجود سجل للمكتب، ثم نزيد القيمة
            $profitSafe = \App\Models\ProfitSafe::firstOrCreate(
                ['office_id' => $transfer->destination_office_id]
            );

            // إضافة قيمة الـ fee المحسوبة إلى عمود profit_main
            $profitSafe->increment('profit_main', $profit);
        }
    }

    // رفع صورة هوية المستلم إن وجدت
    if ($request->hasFile('receiver_id_image')) {
        $path = $request->file('receiver_id_image')->store('receipts', 'public');
        $transfer->receiver_id_image = $path;
    }

    $transfer->status = $request->status;
}

            $transfer->save();
            // ✅ إضافة: إرسال إشعار للزبون عند اكتمال الحوالة
            // داخل TransferController.php في دالة update

            if ($transfer->status === 'completed') {
                $sender = \App\Models\User::find($transfer->sender_id);
                if ($sender && $sender->fcm_token) {
                    $fcmService = new \App\Services\FcmService();

                    $fcmService->sendNotification(
                        $sender->fcm_token,
                        "اكتملت الحوالة! 🎉",
                        "تم تسليم حوالتك رقم ({$transfer->tracking_code}) بنجاح.",
                        [
                            'transfer_id' => (string)$transfer->id,
                            'tracking_code'   => (string)$transfer->tracking_code, // ✅ تم إضافته
                            'current_user_id' => (string)$sender->id,               // ✅ تم إضافته
                            'type'        => 'transfer_completed'
                        ]
                    );
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'تم تحديث الحوالة وتعديل الصناديق بنجاح',
                'data' => $transfer->load(['sender', 'currency'])
            ], 200);
        });
    }

    /**
     * يحسب سعر الصرف الفعلي للعملة بناءً على الشرائح (currency_rates).
     * إن وُجدت شريحة تنطبق على المبلغ → يُعيد سعرها.
     * وإلا → يُعيد السعر الأساسي (currency->price).
     */
    private function getEffectiveRate(Currency $currency, float $amount): float
    {
        $rates = $currency->rates ?? collect();

        if ($rates->isNotEmpty()) {
            $sorted = $rates->sortBy('min_amount')->values();

            foreach ($sorted as $tier) {
                $min = (float) $tier->min_amount;
                $max = $tier->max_amount !== null ? (float) $tier->max_amount : INF;

                if ($amount >= $min && $amount <= $max) {
                    return (float) $tier->rate;
                }
            }
        }

        // fallback: السعر الأساسي من جدول currencies
        return (float) ($currency->price ?? 1);
    }
}
