<?php

namespace App\Http\Controllers;

use App\Models\OfficeSafe;
use Illuminate\Http\Request;

class OfficeSafeController extends Controller
{
    // تحديث رصيد الخزنة (إيداع أو سحب)
    public function updateBalance(Request $request, $officeId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'type'   => 'required|in:deposit,withdraw',
        ]);

        // التعديل هنا: استعلام مباشر عن الخزنة بدلاً من الاعتماد على العلاقات
        $safe = OfficeSafe::where('office_id', $officeId)->first();

        if (!$safe) {
            return response()->json(['message' => 'هذا المكتب لا يملك خزنة!'], 404);
        }

        if ($request->type === 'withdraw' && $safe->balance < abs($request->amount)) {
            return response()->json(['message' => 'الرصيد في الخزنة غير كافٍ!'], 400);
        }

        if ($request->type === 'deposit') {
            $safe->increment('balance', abs($request->amount));
        } else {
            $safe->decrement('balance', abs($request->amount));
        }

        return response()->json([
            'status' => 'success',
            'new_balance' => $safe->balance,
            'message' => 'تم تحديث رصيد الخزنة بنجاح'
        ]);
    }
}
