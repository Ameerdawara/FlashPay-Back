<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;

class OfficeSafeController extends Controller
{
    // تحديث رصيد الخزنة (إيداع أو سحب)
    public function updateBalance(Request $request, $officeId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric', // القيمة (موجبة للإيداع وسالبة للسحب)
            'type'   => 'required|in:deposit,withdraw',
        ]);

        $office = Office::findOrFail($officeId);
        $safe = $office->safe;

        if (!$safe) {
            return response()->json(['message' => 'هذا المكتب لا يملك خزنة!'], 404);
        }

        if ($request->type === 'withdraw' && $safe->balance < abs($request->amount)) {
            return response()->json(['message' => 'الرصيد في الخزنة غير كافٍ!'], 400);
        }

        // التعديل
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