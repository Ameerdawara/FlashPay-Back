<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function index()
    {
        // العملات مع شرائح الدولار (rates) وشرائح السوري (syp_rates)
        return response()->json(Currency::with(['rates', 'sypRates'])->get(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:10|unique:currencies,code',
            'price'          => 'nullable|numeric',
            'main_price'     => 'nullable|numeric',
            'price_syp'      => 'nullable|numeric|min:0',
            'main_price_syp' => 'nullable|numeric|min:0',
        ]);

        $currency = Currency::create($validated);

        return response()->json($currency, 201);
    }

    public function show($id)
    {
        $currency = Currency::with(['rates', 'sypRates'])->findOrFail($id);

        return response()->json($currency, 200);
    }

    /**
     * جلب السعر بناءً على المبلغ والعملة.
     * rate_type: usd (الافتراضي) أو syp
     */
    public function getRate(Request $request)
    {
        $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount'      => 'required|numeric|min:0',
            'rate_type'   => 'nullable|in:usd,syp',
        ]);

        $rateType = $request->input('rate_type', Currency::RATE_USD);
        $amount   = (float) $request->amount;

        $currency = Currency::with(['rates', 'sypRates'])->findOrFail($request->currency_id);

        if (!$currency->supportsRateType($rateType)) {
            return response()->json([
                'status'  => 'error',
                'message' => "لا يوجد سعر صرف بالسوري للعملة {$currency->code}",
            ], 422);
        }

        $rate = $currency->effectiveRate($amount, $rateType);

        return response()->json([
            'status'    => 'success',
            'rate_type' => $rateType,
            'rate'      => $rate,
            'total'     => $amount * $rate,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // تحديث الأسعار الافتراضية (بدون شرائح) — كل نوع مستقل عن الآخر
    // ─────────────────────────────────────────────────────────────────────

    /** سعر البيع بالدولار */
    public function updatePrice(Request $request, $identifier)
    {
        return $this->updatePriceField($request, $identifier, 'price', 'Price');
    }

    /** سعر التكلفة بالدولار */
    public function updateMainPrice(Request $request, $identifier)
    {
        return $this->updatePriceField($request, $identifier, 'main_price', 'Main price');
    }

    /** سعر البيع بالسوري */
    public function updatePriceSyp(Request $request, $identifier)
    {
        return $this->updatePriceField($request, $identifier, 'price_syp', 'SYP price');
    }

    /** سعر التكلفة بالسوري */
    public function updateMainPriceSyp(Request $request, $identifier)
    {
        return $this->updatePriceField($request, $identifier, 'main_price_syp', 'SYP main price');
    }

    // ─────────────────────────────────────────────────────────────────────
    // تحديث شرائح الأسعار (خاص بالسوبر أدمن)
    // ─────────────────────────────────────────────────────────────────────

    /** شرائح الدولار */
    public function updateRates(Request $request, $id)
    {
        return $this->syncRates($request, $id, Currency::RATE_USD);
    }

    /** شرائح السوري */
    public function updateSypRates(Request $request, $id)
    {
        return $this->syncRates($request, $id, Currency::RATE_SYP);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function updatePriceField(Request $request, $identifier, string $field, string $label)
    {
        $request->validate([
            $field => 'required|numeric|min:0',
        ]);

        // رقم → البحث بالـ id، غير ذلك → البحث بالكود
        $currency = ctype_digit((string) $identifier)
            ? Currency::find($identifier)
            : Currency::where('code', $identifier)->first();

        if (!$currency) {
            return response()->json(['status' => 'error', 'message' => 'Currency not found'], 404);
        }

        $currency->update([$field => $request->input($field)]);

        return response()->json([
            'status'  => 'success',
            'message' => "{$label} for {$currency->code} updated successfully",
            'data'    => $currency,
        ], 200);
    }

    private function syncRates(Request $request, $id, string $type)
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $validated = $request->validate([
            'rates'              => 'present|array',
            'rates.*.min_amount' => 'required|numeric|min:0',
            'rates.*.max_amount' => 'nullable|numeric|gt:rates.*.min_amount',
            'rates.*.rate'       => 'required|numeric|min:0',
        ]);

        $currency = Currency::findOrFail($id);

        $tiers = collect($validated['rates'])->map(fn ($tier) => [
            'type'       => $type,
            'min_amount' => $tier['min_amount'],
            'max_amount' => $tier['max_amount'] ?? null,
            'rate'       => $tier['rate'],
        ])->all();

        // حذف شرائح هذا النوع فقط ثم إضافة الجديدة (بشكل ذري)
        DB::transaction(function () use ($currency, $type, $tiers) {
            $relation = $type === Currency::RATE_SYP ? $currency->sypRates() : $currency->rates();
            $relation->delete();
            $relation->createMany($tiers);
        });

        $label = $type === Currency::RATE_SYP ? 'السوري' : 'الدولار';

        return response()->json([
            'status'  => 'success',
            'message' => "تم تحديث شرائح أسعار الصرف بـ{$label} بنجاح",
            'data'    => $currency->load(['rates', 'sypRates']),
        ]);
    }
}