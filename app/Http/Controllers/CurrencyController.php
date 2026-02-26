<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        return response()->json(Currency::all(), 200);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'code'  => 'required|string|max:10|unique:currencies,code',
            'price' => 'nullable|string'
        ]);

        $currency = Currency::create($validated);
        return response()->json($currency, 201);
    }
    public function show($id)
    {
        $currency = Currency::findOrFail($id);
        return response()->json($currency, 200);
    }
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
