<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\CurrencyRate; // تأكد من استدعاء الموديل الجديد
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        // يفضل تحميل الشرائح مع العملات لرؤيتها في الواجهة
        return response()->json(Currency::with('rates')->get(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'code'  => 'required|string|max:10|unique:currencies,code',
            'price' => 'nullable|numeric' // غيرناها لـ numeric لتناسب الحسابات
        ]);

        $currency = Currency::create($validated);
        return response()->json($currency, 201);
    }

    public function show($id)
    {
        $currency = Currency::with('rates')->findOrFail($id);
        return response()->json($currency, 200);
    }

    /**
     * تابع جلب السعر بناءً على المبلغ والعملة (الذي طلبته للروت)
     */
    public function getRate(Request $request)
    {
        $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount'      => 'required|numeric|min:0'
        ]);

        $currencyId = $request->currency_id;
        $amount = $request->amount;

        // البحث عن الشريحة المناسبة
        $tier = CurrencyRate::where('currency_id', $currencyId)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                      ->orWhereNull('max_amount');
            })->first();

        if ($tier) {
            $rate = $tier->rate;
        } else {
            // السعر الافتراضي من جدول العملات
            $currency = Currency::find($currencyId);
            $rate = $currency->price ?? 1;
        }

        return response()->json([
            'status' => 'success',
            'rate'   => (float)$rate,
            'total'  => (float)($amount * $rate)
        ]);
    }

    public function updatePrice(Request $request, $identifier)
    {
        $request->validate([
            'price' => 'required|numeric'
        ]);

        $currency = Currency::where('id', $identifier)
            ->orWhere('code', $identifier)
            ->first();

        if (!$currency) {
            return response()->json(['status' => 'error', 'message' => 'Currency not found'], 404);
        }

        $currency->update(['price' => $request->price]);

        return response()->json([
            'status' => 'success',
            'message' => "Price for {$currency->code} updated successfully",
            'data' => $currency
        ], 200);
    }

    /**
     * تحديث شرائح الأسعار (خاص بالسوبر أدمن)
     */
    public function updateRates(Request $request, $id)
    {
        
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $request->validate([
            'rates'              => 'required|array',
            'rates.*.min_amount' => 'required|numeric|min:0',
            'rates.*.max_amount' => 'nullable|numeric|gt:rates.*.min_amount',
            'rates.*.rate'       => 'required|numeric|min:0',
        ]);

        $currency = Currency::findOrFail($id);

        // حذف قديم وإضافة جديد
        $currency->rates()->delete();
        $currency->rates()->createMany($request->rates);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث شرائح أسعار الصرف بنجاح',
            'data' => $currency->load('rates')
        ]);
    }
}