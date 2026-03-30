<?php

namespace App\Http\Controllers;

use App\Models\InternalTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InternalTransferController extends Controller
{
    // عرض الحوالات الخاصة بمكتب الموظف المسجل حالياً فقط
    public function index()
    {
        $user = Auth::user();
        
        $transfers = InternalTransfer::where('office_id', $user->office_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transfers
        ]);
    }

    public function store(Request $request)
    {
        // 1. التحقق من الحقول القادمة من الفرونت (المرسل، المستلم، المبلغ، العمولة، وحالة الدفع)
        $validated = $request->validate([
            'sender_name'   => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0.01',
            'commission'    => 'required|numeric|min:0',
            'is_paid'       => 'required|boolean', // يتم استقبالها من الـ Radio Button
        ]);

      
        $user = Auth::user();

        // 3. دمج الحقول التلقائية (المكتب والتاريخ الحالي) مع البيانات المعتمدة
        $transferData = array_merge($validated, [
            'office_id'     => $user->office_id,    // مكتب الموظف تلقائياً
            'transfer_date' => now()->toDateString(), // تاريخ اليوم تلقائياً
        ]);

        // 4. الحفظ في قاعدة البيانات
        $transfer = InternalTransfer::create($transferData);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الحوالة الداخلية بنجاح',
            'data' => $transfer
        ], 201);
    }

    // تابع تغيير الحالة (مفيد جداً إذا أراد الموظف تحديث حالة الدفع لاحقاً)
    public function togglePaidStatus($id)
    {
        $transfer = InternalTransfer::findOrFail($id);
        
        // حماية: التأكد أن الموظف لا يغير حالة حوالة لمكتب آخر
        if ($transfer->office_id !== Auth::user()->office_id) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $transfer->is_paid = !$transfer->is_paid;
        $transfer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث حالة الدفع',
            'data' => $transfer
        ]);
    }
}