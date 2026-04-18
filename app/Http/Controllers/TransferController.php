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
    $user  = Auth::user();
    $query = Transfer::query();

    // إذا طلب المستخدم الحوالات المؤرشفة تحديداً
    if ($request->has('archived') && $request->archived == 1) {
        $query->where('status', 'archived');
    } else {
        // الافتراضي: جلب كل شيء ما عدا المؤرشف لكي لا تزدحم الشاشة
        $query->where('status', '!=', 'archived');
    }

    if ($request->has('status') && $request->status !== 'archived') {
        $query->where('status', $request->status);
    }

    $query->with(['sender.country', 'currency', 'sendCurrency', 'destinationOffice']);

    if ($user->role !== 'super_admin') {
        $query->where('destination_office_id', $user->office_id);
    }

    $transfers = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'status' => 'success',
        'data'   => $transfers,
    ], 200);
}

    // ─────────────────────────────────────────────────────────────────────
    // إنشاء حوالة عادية (زبون / كاشير)
    // ─────────────────────────────────────────────────────────────────────
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

        $currency    = Currency::with('rates')->findOrFail($validated['send_currency_id']);
        $amountInUsd = $validated['amount'] * $this->getEffectiveRate($currency, $validated['amount']);

        $transfer = Transfer::create([
            'tracking_code'          => $trackingCode,
            'sender_id'              => $request->input('sender_id', Auth::id()),
            'amount'                 => $validated['amount'],
            'amount_in_usd'          => $amountInUsd,
            'currency_id'            => $validated['currency_id'],
            'send_currency_id'       => $validated['send_currency_id'],
            'destination_office_id'  => $validated['destination_office_id'] ?? null,
            'destination_country_id' => $validated['destination_country_id'] ?? null,
            'destination_city'       => $validated['destination_city'] ?? null,
            'receiver_name'          => $validated['receiver_name'],
            'receiver_phone'         => $validated['receiver_phone'],
            'status'                 => $request->input('status', 'waiting'),
            'fee'                    => 0,
            'receiver_id_image'      => null,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Transfer created successfully',
            'data'    => $transfer,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // إنشاء حوالة الوكيل (agent)
    // - الأموال تذهب إلى super_safe
    // - نسبة من fee تذهب إلى صندوق المندوب (main_safe)
    // - الباقي يذهب إلى super_safe
    // ─────────────────────────────────────────────────────────────────────
    public function storeAgentTransfer(Request $request)
    {
        $validated = $request->validate([
            'amount'                => 'required|numeric|min:1',
            'currency_id'           => 'required|exists:currencies,id',
            'send_currency_id'      => 'required|exists:currencies,id',
            'receiver_name'         => 'required|string|max:255',
            'receiver_phone'        => 'required|string|max:20',
            'destination_city'      => 'required|string',
            'destination_office_id' => 'required|exists:offices,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $agent    = Auth::user();
            $currency = Currency::with('rates')->findOrFail($validated['send_currency_id']);
            $amount   = (float) $validated['amount'];

            $effectiveRate = $this->getEffectiveRate($currency, $amount);
            $amountInUsd   = $amount * $effectiveRate;

            // ── حساب الـ fee (ربح الفرق بين سعر البيع وسعر التكلفة) ──
            // $priceDiff = $effectiveRate - (float) ($currency->main_price ?? 0);
            // $totalFee  = max(0, $amount * $priceDiff);

            // ── صندوق المندوب (main_safe) ──────────────────────────────
            $agentSafe = MainSafe::where('owner_type', 'App\\Models\\User')
                ->where('owner_id', $agent->id)
                ->lockForUpdate()
                ->first();

            // نسبة ربح المندوب من الـ fee
            $agentRatio  = $agentSafe ? (float) $agentSafe->agent_profit_ratio : 0;
            $agentProfit = $amountInUsd * ($agentRatio / 100);
            // $superProfit = $totalFee - $agentProfit;

            $trackingCode = 'TRX-' . strtoupper(Str::random(8));

            // ── إنشاء الحوالة ──────────────────────────────────────────
            $transfer = Transfer::create([
                'tracking_code'         => $trackingCode,
                'sender_id'             => $agent->id,
                'amount'                => $amount,
                'amount_in_usd'         => $amountInUsd,
                'currency_id'           => $validated['currency_id'],
                'send_currency_id'      => $validated['send_currency_id'],
                'destination_office_id' => $validated['destination_office_id'],
                'destination_city'      => $validated['destination_city'],
                'receiver_name'         => $validated['receiver_name'],
                'receiver_phone'        => $validated['receiver_phone'],
                'status'                => 'ready',
                'fee'                   => 0,
            ]);

            // ── تحديث super_safe (المبلغ الكامل + حصة السوبر من الربح) ──
            // $superSafe     = \App\Models\SuperSafe::instance()->lockForUpdate()->first()
            //                  ?? \App\Models\SuperSafe::instance();
            // $balanceBefore = $superSafe->balance;

            // $superSafe->increment('balance', $amountInUsd+$superProfit);

            // \App\Models\SuperSafeLog::create([
            //     'type'           => 'deposit',
            //     'amount'         => $amountInUsd,
            //     'office_id'      => null,
            //     'office_name'    => 'وكيل - ' . $agent->name,
            //     'note'           => "استلام حوالة وكيل | كود: {$trackingCode} | ربح السوبر: " . number_format($superProfit, 2),
            //     'balance_before' => $balanceBefore,
            //     'balance_after'  => $superSafe->fresh()->balance,
            // ]);

            // ── تحديث صندوق المندوب (agent_profit فقط ، المبلغ يبقى في super_safe) ──
            if ($agentSafe && $agentProfit > 0) {
                $agentSafe->increment('agent_profit', $agentProfit);
            }

            return response()->json([
                'status'       => 'success',
                'message'      => 'تم إنشاء الحوالة بنجاح',
                'data'         => $transfer,

            ], 201);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // تعديل حوالة (admin / super_admin)
    // ─────────────────────────────────────────────────────────────────────
    public function editTransfer(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user     = Auth::user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك بتعديل الحوالة'], 403);
        }

        $validated = $request->validate([
            'receiver_name'  => 'sometimes|string|max:255',
            'receiver_phone' => 'sometimes|string|max:20',
            'amount'         => 'sometimes|numeric|min:1',
            'fee'            => 'sometimes|numeric|min:0',
            'notes'          => 'sometimes|nullable|string|max:500',
        ]);

        $updateFields = array_filter($validated, fn($k) => $k !== 'notes', ARRAY_FILTER_USE_KEY);

        if (isset($updateFields['amount'])) {
            $currency = Currency::with('rates')->find($transfer->send_currency_id);
            if ($currency) {
                $updateFields['amount_in_usd'] = $updateFields['amount']
                    * $this->getEffectiveRate($currency, $updateFields['amount']);
            }
        }

        $transfer->update($updateFields);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تعديل الحوالة بنجاح',
            'data'    => $transfer->load(['sender', 'currency', 'sendCurrency']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // سجل تعديلات الحوالة
    // ─────────────────────────────────────────────────────────────────────
    public function transferHistory(Request $request, $id = null)
    {
        $user = Auth::user();

        if ($id) {
            $history = TransferHistory::where('transfer_id', $id)
                ->with('editor')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json(['message' => 'غير مصرح'], 403);
            }
            $history = TransferHistory::with(['transfer', 'editor'])
                ->orderBy('created_at', 'desc')
                ->take(200)
                ->get();
        }

        return response()->json(['status' => 'success', 'data' => $history]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // تحديث حالة الحوالة + احتساب الأرباح
    // ─────────────────────────────────────────────────────────────────────
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
                // إرسال إشعار للزبون بأن الحوالة جاهزة
                $customer = \App\Models\User::find($transfer->sender_id);
                // if ($customer && $customer->fcm_token) {
                //     $fcmService = new \App\Services\FcmService();
                //     $fcmService->sendNotification(
                //         $customer->fcm_token,
                //         "حوالتك جاهزة! ✅",
                //         "طلبك للحوالة رقم ({$transfer->tracking_code}) أصبح جاهزاً للاستلام.",
                //         [
                //             'transfer_id' => (string)$transfer->id,
                //             'type'        => 'transfer_ready',
                //             'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                //         ]
                //     );
                // }

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

                // جلب بيانات مرسل الحوالة للتحقق من دوره
                $sender = \App\Models\User::find($transfer->sender_id);
                $isAgent = $sender && $sender->role === 'agent';

                if ($isAgent) {
                    // 1. حالة حوالة الوكيل (Agent)
                    // سحب المبلغ من صندوق السوبر (SuperSafe) بدون المساس بالأرباح (لأنها محسوبة ومضافة مسبقاً)
                   $officeSafe = \App\Models\MainSafe::where('owner_id', $transfer->destination_office_id)
                        ->where('owner_type', 'App\Models\Office')
                        ->lockForUpdate()
                        ->first();

                    if (!$officeSafe) throw new \Exception("صندوق المكتب غير موجود");
                    if ($officeSafe->balance < $transfer->amount_in_usd) {
                        throw new \Exception("رصيد صندوق المكتب غير كافٍ لتسليم الحوالة");
                    }

                    $officeSafe->decrement('balance', $transfer->amount_in_usd);
                    // توثيق عملية السحب من السوبر أدمن (مستحسن لضبط الحسابات)
                    \App\Models\SuperSafeLog::create([
                        'type'           => 'withdraw',
                        'amount'         => $transfer->amount_in_usd,
                        'office_id'      => $transfer->destination_office_id,
                        'office_name'    => 'تسليم حوالة وكيل',
                        'note'           => "سحب لتسليم حوالة وكيل | كود: {$transfer->tracking_code}",
                       
                    ]);

                } else {
                    // 2. حالة حوالة الزبون العادي (ليس وكيل)
                    // تحديث رصيد الصندوق الرئيسي للمكتب وحساب الأرباح
                    $officeSafe = \App\Models\MainSafe::where('owner_id', $transfer->destination_office_id)
                        ->where('owner_type', 'App\Models\Office')
                        ->lockForUpdate()
                        ->first();

                    if (!$officeSafe) throw new \Exception("صندوق المكتب غير موجود");
                    if ($officeSafe->balance < $transfer->amount_in_usd) {
                        throw new \Exception("رصيد صندوق المكتب غير كافٍ لتسليم الحوالة");
                    }

                    $officeSafe->decrement('balance', $transfer->amount_in_usd);
                }
                    // حساب الربح
                    $currency = \App\Models\Currency::find($transfer->send_currency_id);
                    if ($currency) {
                        $amount = $transfer->amount;
                        $tier = \App\Models\CurrencyRate::where('currency_id', $currency->id)
                            ->where('min_amount', '<=', $amount)
                            ->where(function ($query) use ($amount) {
                                $query->where('max_amount', '>=', $amount)
                                      ->orWhereNull('max_amount');
                            })->first();

                        $appliedRate = $tier ? (float)$tier->rate : (float)$currency->price;
                        $priceDiff = abs($appliedRate - (float)$currency->main_price);
                        $profit = $amount * $priceDiff;

                        $transfer->fee = $profit;

                        $profitSafe = \App\Models\ProfitSafe::firstOrCreate(
                            ['office_id' => $transfer->destination_office_id]
                        );
                        $profitSafe->increment('profit_main', $profit);
                    }
                

                // --- الإجراءات المشتركة (رفع الصورة وتحديث الحالة) ---

         if ($request->hasFile('receiver_id_image')) {
                    $path = $request->file('receiver_id_image')->store('receipts', 'public');
                    $transfer->receiver_id_image = $path;
                }

                $transfer->status = $request->status;
            }
        }

        // حفظ التغييرات النهائية
        $transfer->save();

        // إشعار اكتمال الحوالة
        // if ($transfer->status === 'completed') {
        //     $sender = \App\Models\User::find($transfer->sender_id);
        //     if ($sender && $sender->fcm_token) {
        //         $fcmService = new \App\Services\FcmService();
        //         $fcmService->sendNotification(
        //             $sender->fcm_token,
        //             'اكتملت الحوالة! 🎉',
        //             "تم تسليم حوالتك رقم ({$transfer->tracking_code}) بنجاح.",
        //             [
        //                 'transfer_id'     => (string) $transfer->id,
        //                 'tracking_code'   => (string) $transfer->tracking_code,
        //                 'current_user_id' => (string) $sender->id,
        //                 'type'            => 'transfer_completed',
        //             ]
        //         );
        //     }
        // }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث الحوالة وتعديل الصناديق بنجاح',
            'data'    => $transfer->load(['sender', 'currency','sender.country']),
        ], 200);
    });
}
    // ─────────────────────────────────────────────────────────────────────
    // GET /agent/safe  — رصيد وسجل الصندوق الخاص بالمندوب
    // ─────────────────────────────────────────────────────────────────────
    public function agentSafeDetails(Request $request)
    {
        $agent = $request->user();

        if ($agent->role !== 'agent') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $safe = MainSafe::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $agent->id)
            ->first();

        // آخر 50 حوالة للمندوب
        $transfers = Transfer::where('sender_id', $agent->id)
            ->with(['currency', 'sendCurrency', 'destinationOffice'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'balance'            => $safe ? (float) $safe->balance : 0,
                'agent_profit'       => $safe ? (float) $safe->agent_profit : 0,
                'agent_profit_ratio' => $safe ? (float) $safe->agent_profit_ratio : 0,
                'transfers'          => $transfers,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PATCH /agent/safe/update-ratio  (super_admin فقط)
    // تحديث نسبة ربح المندوب
    // ─────────────────────────────────────────────────────────────────────
    public function updateAgentProfitRatio(Request $request)
    {
        if (Auth::user()->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'agent_id'           => 'required|exists:users,id',
            'agent_profit_ratio' => 'required|numeric|min:0|max:100',
        ]);

        $safe = MainSafe::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $validated['agent_id'])
            ->first();

        if (!$safe) {
            $safe = MainSafe::create([
                'owner_type'         => 'App\\Models\\User',
                'owner_id'           => $validated['agent_id'],
                'balance'            => 0,
                'agent_profit_ratio' => $validated['agent_profit_ratio'],
                'agent_profit'       => 0,
            ]);
        } else {
            $safe->update(['agent_profit_ratio' => $validated['agent_profit_ratio']]);
        }

        return response()->json([
            'status'             => 'success',
            'message'            => 'تم تحديث نسبة ربح المندوب بنجاح',
            'agent_profit_ratio' => (float) $safe->agent_profit_ratio,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // سعر الصرف الفعلي (شرائح أو افتراضي)
    // ─────────────────────────────────────────────────────────────────────
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

        return (float) ($currency->price ?? 1);
    }
}
