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
        $transfer = Transfer::create([
            'tracking_code'         => $trackingCode,
            'sender_id'             => Auth::id(), // سحب الـ id للمستخدم المسجل دخوله
            'amount'                => $validated['amount'],
            'currency_id'           => $validated['currency_id'],
            'destination_office_id' => $validated['destination_office_id'] ?? null,
            'destination_agent_id'  => $validated['destination_agent_id'] ?? null,
            'receiver_name'         => $validated['receiver_name'],
            'receiver_phone'        => $validated['receiver_phone'],
            'status'                => 'pending', 
            'fee'                   => 0, // الرسوم تبقى 0 (أو null حسب الداتا بيز)
            'receiver_id_image'     => null, // الصورة تبقى null
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

        // نلف العملية كلها داخل Transaction لضمان سلامة البيانات المالية
        return DB::transaction(function () use ($request, $transfer, $user) {

            // 1. المعتمد (Agent) يحول من pending إلى approved أو waiting
            if ($user->role === 'agent') {
                $request->validate(['status' => 'required|in:approved,waiting']);

                // الحالة: الزبون سلم المال للوكيل -> يزيد رصيد صندوق الوكيل
                if ($request->status === 'approved' && $transfer->status === 'pending') {
                    $agentSafe = MainSafe::where('owner_id', $user->id)
                                        ->where('owner_type', 'App\Models\User')
                                        ->first();
                    
                    if (!$agentSafe) throw new \Exception("صندوق الوكيل غير موجود");
                    $agentSafe->increment('balance', $transfer->amount);
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

                    if (!$agentSafe || !$officeSafe) throw new \Exception("أحد الصناديق (وكيل أو مكتب) غير موجود");

                    $agentSafe->decrement('balance', $transfer->amount);
                    $officeSafe->increment('balance', $transfer->amount);
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
                    $officeSafe->decrement('balance', $transfer->amount);
                }

                // رفع صورة الهوية
                if ($request->hasFile('receiver_id_image')) {
                    $path = $request->file('receiver_id_image')->store('receipts', 'public');
                    $transfer->receiver_id_image = $path;
                }

                $transfer->status = $request->status;
            }

            else {
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
