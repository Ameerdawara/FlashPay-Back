<?php

namespace App\Http\Controllers;

use App\Models\InternalTransfer;
use Illuminate\Http\Request;

class InternalTransferController extends Controller
{
    // عرض الحوالات الداخلية لمكتب معين
    public function index(Request $request)
    {
        // إذا أردت جلب حوالات مكتب محدد أرسل office_id في الطلب
        $query = InternalTransfer::with('office')->orderBy('transfer_date', 'desc');

        if ($request->has('office_id')) {
            $query->where('office_id', $request->office_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);


    }

    // إنشاء حوالة داخلية جديدة
    public function store(Request $request)
    {
        $request->validate([
            'office_id'      => 'nullable|exists:offices,id',
            'sender_name'    => 'required|string|max:255',
            'receiver_name'  => 'required|string|max:255',
            'receiver_phone' => 'nullable|string|max:30',
            'destination_province' => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0.01',
            'commission'     => 'required|numeric|min:0',
            'currency'       => 'required|string|max:10',
            'fee_payer'      => 'required|in:sender,receiver',
            'is_paid'        => 'boolean',
            'transfer_date'  => 'required|date',
        ]);

        $transfer = InternalTransfer::create($request->all());
           if (!empty($validated['commission']) && $validated['commission'] > 0) {
        $officeId = $validated['office_id'] ?? auth()->user()->office_id;

        if ($officeId) {
            $profitSafe = \App\Models\ProfitSafe::firstOrCreate(
                ['office_id' => $officeId],
                ['profit_trade' => 0, 'profit_main' => 0]
            );
            $profitSafe->increment('profit_main', $validated['commission']);
        }
    }
        return response()->json([
            'status'  => 'success',
            'message' => 'تم إنشاء الحوالة الداخلية بنجاح',
            'data'    => $transfer
        ], 201);
    }

    // تغيير حالة الدفع (من غير مدفوع إلى مدفوع والعكس)
    public function togglePaidStatus($id)
    {
        $transfer = InternalTransfer::findOrFail($id);
        $transfer->is_paid = !$transfer->is_paid;
        $transfer->save();

        return response()->json([
            'status' => 'success',
            'message' => $transfer->is_paid ? 'تم تسليم الحوالة' : 'تم إلغاء تسليم الحوالة',
            'data' => $transfer
        ]);
    }
}
