<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\MainSafe;
use App\Models\User;
use App\Models\Office;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Transfer::query();

        // 1. أمان: إذا كان المستخدم وكيل، اجلب الحوالات الموجهة له فقط
        if ($user->role === 'agent') {
            $query->where('destination_agent_id', $user->id);
        }

        // الفلترة حسب الحالة الممررة في الرابط (?status=pending)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // جلب الحوالات مع بيانات المرسل والعملة، وترتيبها من الأحدث للأقدم
        $transfers = $query->with(['sender', 'currency','sendCurrency'])->orderBy('created_at', 'desc')->get();

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
        // 1. التحقق من صحة البيانات
        $validated = $request->validate([
            'amount'                => 'required|numeric|min:1',
            'currency_id'           => 'required|exists:currencies,id',
            'destination_office_id' => 'exists:offices,id|required',
            'destination_agent_id'  => 'exists:users,id|required',
            'receiver_name'         => 'required|string|max:255',
            'receiver_phone'        => 'required|string|max:20',
        ]);

        // 2. توليد كود تتبع عشوائي فريد (مثلاً: TRX-A8F9K2)
        $trackingCode = 'TRX-' . strtoupper(Str::random(8));

        // 3. إنشاء الحوالة


        // 1. جلب العملة لمعرفة سعر صرفها الحالي
        $currency = \App\Models\Currency::findOrFail($validated['currency_id']);

        // 2. حساب القيمة بالدولار (المبلغ / سعر الصرف)
        $amountInUsd = $validated['amount'] / $currency->price;

        // 3. إنشاء الحوالة
        $transfer = Transfer::create([
            'tracking_code'         => $trackingCode,
            'sender_id'             => Auth::id(),
            'amount'                => $validated['amount'],
            'amount_in_usd'         => $amountInUsd, // <-- حفظنا القيمة بالدولار هنا
            'currency_id'           => $validated['currency_id'],
            'destination_office_id' => $validated['destination_office_id'] ?? null,
            'destination_agent_id'  => $validated['destination_agent_id'] ?? null,
            'receiver_name'         => $validated['receiver_name'],
            'receiver_phone'        => $validated['receiver_phone'],
            'status'                => 'pending',
            'fee'                   => 0,
            'receiver_id_image'     => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transfer created successfully',
            'data' => $transfer
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user = Auth::user();

        return DB::transaction(function () use ($request, $transfer, $user) {

            // 1. المعتمد (Agent) يحول من pending إلى approved أو rejected
            if ($user->role === 'agent') {
                // أضفنا 'rejected' هنا ليتمكن من الرفض
                $request->validate(['status' => 'required|in:approved,waiting,rejected']);

                // الحالة: الزبون سلم المال للوكيل -> يزيد رصيد صندوق الوكيل
                if ($request->status === 'approved' && $transfer->status === 'pending') {
                    $agentSafe = MainSafe::where('owner_id', $user->id)
                        ->where('owner_type', 'App\Models\User')
                        ->first();

                    if (!$agentSafe) throw new \Exception("صندوق الوكيل غير موجود");
                    $agentSafe->increment('balance', $transfer->amount_in_usd);
                }

                $transfer->status = $request->status;
            }

            // 2. أدمن أو سوبر أدمن يحول إلى ready
            elseif (in_array($user->role, ['admin', 'super_admin'])) {
                $request->validate([
                    'status' => 'required|in:ready',
                    'fee'    => 'required|numeric|min:0'
                ]);

                // الحالة: الحوالة أصبحت جاهزة للتسليم في سوريا
                // نقل المبلغ من عهدة الوكيل إلى عهدة المكتب
                if ($request->status === 'ready' && $transfer->status === 'waiting') {
                    // خصم من صندوق الوكيل (الذي استلم الحوالة أصلاً)
                    $agentSafe = MainSafe::where('owner_id', $transfer->destination_agent_id)
                        ->where('owner_type', 'App\Models\User')
                        ->first();

                    // زيادة في صندوق المكتب المستلم (الذي سيسلم الكاش)
                    $officeSafe = MainSafe::where('owner_id', $transfer->destination_office_id)
                        ->where('owner_type', 'App\Models\Office')
                        ->first();

                    if (!$agentSafe) {
                        throw new \Exception("صندوق الوكيل رقم ({$transfer->destination_agent_id}) غير موجود في النظام.");
                    }

                    if (!$officeSafe) {
                        throw new \Exception("صندوق المكتب رقم ({$transfer->destination_office_id}) غير موجود في النظام.");
                    }
                    $agentSafe->decrement('balance', $transfer->amount_in_usd);
                    $officeSafe->increment('balance', $transfer->amount_in_usd);
                }

                $transfer->status = $request->status;
                $transfer->fee = $request->fee;
            }

            // 3. كاشير أو محاسب يحول إلى completed
            elseif (in_array($user->role, ['cashier', 'accountant'])) {
                $request->validate([
                    'status'            => 'required|in:completed',
                    'receiver_id_image' => 'required|image|mimes:jpeg,png,jpg|max:4096'
                ]);

                // الحالة: المكتب سلم الكاش فعلياً للمستلم -> خصم من رصيد صندوق المكتب
                if ($request->status === 'completed' && $transfer->status === 'ready') {
                    $officeSafe = MainSafe::where('owner_id', $transfer->destination_office_id)
                        ->where('owner_type', 'App\Models\Office')
                        ->first();

                    if (!$officeSafe) throw new \Exception("صندوق المكتب غير موجود");
                    $officeSafe->decrement('balance', $transfer->amount_in_usd);
                }

                // رفع صورة الهوية
                if ($request->hasFile('receiver_id_image')) {
                    $path = $request->file('receiver_id_image')->store('receipts', 'public');
                    $transfer->receiver_id_image = $path;
                }

                $transfer->status = $request->status;
            } else {
                return response()->json(['message' => 'ليس لديك صلاحية'], 403);
            }

            $transfer->save();

            return response()->json([
                'status' => 'success',
                'message' => 'تم تحديث الحوالة وتعديل الصناديق بنجاح',
                'data' => $transfer->load(['sender', 'currency']) // تحميل علاقات إضافية إذا رغبت
            ], 200);
        });
    }
}
