<?php
namespace App\Http\Controllers;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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
            // يجب تحديد إما مكتب استلام أو وكيل استلام
            'destination_office_id' => 'nullable|exists:offices,id|required_without:destination_agent_id',
            'destination_agent_id'  => 'nullable|exists:users,id|required_without:destination_office_id',
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

    /**
     * تحديث حالة الحوالة بناءً على الصلاحيات (Role)
     */
    public function update(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);
        $user = Auth::user();

        // 1 (agent)
        if ($user->role === 'agent') {
            $request->validate([
                'status' => 'required|in:pending,waiting'
            ]);
            $transfer->status = $request->status;
        }

        // 2.  أدمن أو سوبر أدمن
        elseif (in_array($user->role, ['admin', 'super_admin'])) {
            //  إدخال الرسوم (fee)
            $request->validate([
                'status' => 'required|in:ready',
                'fee'    => 'required|numeric|min:0'
            ]);
            $transfer->status = $request->status;
            $transfer->fee = $request->fee;
        }

        // 3.  كاشير أو محاسب
        elseif (in_array($user->role, ['cashier', 'accountant'])) {
            // يحول الحالة إلى completed، ويجب رفع صورة هوية المستلم
            $request->validate([
                'status'            => 'required|in:completed',
                'receiver_id_image' => 'required|image|mimes:jpeg,png,jpg|max:4096' 
            ]);

            // معالجة رفع الصورة
            if ($request->hasFile('receiver_id_image')) {
                $file = $request->file('receiver_id_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                // تخزين الصورة في مجلد public/receipts
                $path = $file->storeAs('receipts', $filename, 'public');
                $transfer->receiver_id_image = $path;
            }

            $transfer->status = $request->status;
        }

        // 4. إذا لم يكن لديه صلاحية
        else {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this transfer'
            ], 403);
        }

        // حفظ التعديلات في قاعدة البيانات
        $transfer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Transfer updated successfully',
            'data' => $transfer
        ], 200);
    }
}
