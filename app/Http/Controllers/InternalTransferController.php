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
        $request->validate([
            'office_id'     => 'required|exists:offices,id',
            'sender_name'   => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0.01',
            'commission'    => 'required|numeric|min:0',
            'is_paid'       => 'boolean',
            'transfer_date' => 'required|date',
        ]);

        $transfer = InternalTransfer::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء الحوالة الداخلية بنجاح',
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
