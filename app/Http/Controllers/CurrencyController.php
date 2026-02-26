<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{

    /**
     * تحديث سعر العملة فقط باستخدام المعرف أو الكود
     */
    public function updatePrice(Request $request, $identifier)
    {
        // 1. التحقق من صحة السعر المرسل
        $request->validate([
            'price' => 'required|numeric'
        ]);

        // 2. البحث عن العملة سواء كان المعرف ID رقمي أو Code نصي
        $currency = Currency::where('id', $identifier)
                            ->orWhere('code', $identifier)
                            ->first();

        if (!$currency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Currency not found'
            ], 404);
        }

        // 3. تحديث السعر فقط
        $currency->update([
            'price' => $request->price
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Price for {$currency->code} updated successfully",
            'data' => $currency
        ], 200);
    }
}
