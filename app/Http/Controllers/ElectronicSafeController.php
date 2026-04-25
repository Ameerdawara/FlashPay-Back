<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ElectronicSafe;
use App\Models\ElectronicSafeLog;
use App\Models\OfficeSafe;

class ElectronicSafeController extends Controller
{
    /* ═══════════════════════════════════════════════════
       GET /electronic-safe/balances
       جلب أرصدة الخزنة الإلكترونية للمكتب الحالي
    ═══════════════════════════════════════════════════ */
    public function getBalances(Request $request)
    {
        $user  = Auth::user();
        $eSafe = ElectronicSafe::where('office_id', $user->office_id)->first();

        if (!$eSafe) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'syp_sham_cash' => 0,
                    'usd_sham_cash' => 0,
                    'usdt'          => 0,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'syp_sham_cash' => (float) $eSafe->syp_sham_cash,
                'usd_sham_cash' => (float) $eSafe->usd_sham_cash,
                'usdt'          => (float) $eSafe->usdt,
            ],
        ]);
    }

    /* ═══════════════════════════════════════════════════
       POST /electronic-safe/buy
       شراء: خصم من خزنة المكتب → زيادة في الخزنة الإلكترونية

       الحقول:
         currency_type   : syp_sham_cash | usd_sham_cash | usdt
         amount          : الكمية المراد شراؤها (بالعملة الإلكترونية)
         commission_rate : نسبة العمولة % (يحددها المستخدم)
         exchange_rate   : سعر الصرف
                           syp_sham_cash → ل.س لكل $1  (مثال: 14000)
                           usd_sham_cash → 1 (دولار = دولار)
                           usdt          → سعر USDT بالدولار (مثال: 0.99)
         note            : ملاحظة اختيارية
    ═══════════════════════════════════════════════════ */
    public function buy(Request $request)
{
    $request->validate([
        'currency_type'   => 'required|in:syp_sham_cash,usd_sham_cash,usdt',
        'amount'          => 'required|numeric|min:0.01',
        'commission_rate' => 'required|numeric|min:0|max:100',
        // 'exchange_rate'   => 'required|numeric|min:0.000001', // اختياري للتوثيق
        'note'            => 'nullable|string|max:500',
    ]);

    return DB::transaction(function () use ($request) {
        $user = Auth::user();
        $amount = (float) $request->amount; // المبلغ المدخل (مثلاً 1000)
        $commRate = (float) $request->commission_rate; // النسبة (مثلاً 2%)
        $currencyType = $request->currency_type;

        // 1. حساب المربح والمبلغ الصافي الذي سيخصم من الخزنة الورقية
        // إذا ادخلت 1000 والنسبة 2% -> المربح 20 والخصم من الخزنة الورقية 980
        $profit = ($amount * $commRate) / 100;
        $netToDeduct = $amount - $profit;

        // 2. تحديد مصدر الخصم من خزنة المكتب (OfficeSafe)
        // سوري شام كاش -> يخصم من balance_sy | دولار شام كاش أو USDT -> يخصم من balance
        $isSyrian = ($currencyType === 'syp_sham_cash');
        $officeSafeField = $isSyrian ? 'balance_sy' : 'balance';

        // 3. جلب خزنة المكتب والتحقق من الرصيد الورقي (المصدر)
        $officeSafe = OfficeSafe::where('office_id', $user->office_id)->lockForUpdate()->first();

        if (!$officeSafe || $officeSafe->$officeSafeField < $netToDeduct) {
            $currencyLabel = $isSyrian ? 'ليرة سورية' : 'دولار';
            return response()->json([
                'status'  => 'error',
                'message' => "رصيد الخزنة الورقية ($currencyLabel) غير كافٍ. المطلوب خصم: $netToDeduct"
            ], 400);
        }

        // 4. الخصم من خزنة المكتب (الصافي فقط)
        $officeSafe->decrement($officeSafeField, $netToDeduct);

        // 5. الزيادة في الخزنة الإلكترونية (المبلغ كاملاً)
        $eSafe = ElectronicSafe::firstOrCreate(['office_id' => $user->office_id]);
        $eSafe->increment($currencyType, $amount);

        // 6. توثيق العملية في السجلات (بدون زيادة جداول أرباح خارجية)
        $log = ElectronicSafeLog::create([
            'office_id'       => $user->office_id,
            'currency_type'   => $currencyType,
            'action_type'     => 'buy',
            'amount'          => $amount,       // 1000
            'commission_rate' => $commRate,     // 2%
            'net_amount'      => $netToDeduct,  // 980 (المخصوم فعلياً)
            'profit'          => $profit,      // 20 (الموثق كربح)
            'note'            => $request->note ?? "شراء {$currencyType} بخصم عمولة {$commRate}%",
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تمت عملية الشراء بنجاح',
            'details' => [
                'amount_added_to_esafe' => $amount,
                'amount_deducted_from_office' => $netToDeduct,
                'profit_documented' => $profit,
                'office_balance_remaining' => $officeSafe->fresh()->$officeSafeField,
            ],
        ], 200);
    });
}

    /* ═══════════════════════════════════════════════════
       POST /electronic-safe/sell
       بيع: خصم من الخزنة الإلكترونية → زيادة في خزنة المكتب
    ═══════════════════════════════════════════════════ */
  public function sell(Request $request)
{
    $request->validate([
        'currency_type'   => 'required|in:syp_sham_cash,usd_sham_cash,usdt',
        'amount'          => 'required|numeric|min:0.01',
        'commission_rate' => 'required|numeric|min:0|max:100',
        'exchange_rate'   => 'required|numeric|min:0.000001',
        'note'            => 'nullable|string|max:500',
    ]);

    return DB::transaction(function () use ($request) {
        $user         = Auth::user();
        $amount       = (float) $request->amount;
        $commRate     = (float) $request->commission_rate;
        $exchangeRate = (float) $request->exchange_rate;
        $currencyType = $request->currency_type;
        $note         = $request->note ?? null;

        // 1. حساب الربح والمبلغ الإجمالي (الذي سيدفعه الزبون للمكتب)
        $profit = ($amount * $commRate) / 100;
        $totalToDeposit = $amount + $profit;

        // 2. التحقق من رصيد الخزنة الإلكترونية وخصمه
        $eSafe = ElectronicSafe::where('office_id', $user->office_id)->lockForUpdate()->first();

        if (!$eSafe || $eSafe->$currencyType < $amount) {
            return response()->json([
                'status'  => 'error',
                'message' => 'رصيد الخزنة الإلكترونية غير كافٍ — المتاح: ' . number_format($eSafe?->$currencyType ?? 0, 2),
            ], 400);
        }

        $eSafe->decrement($currencyType, $amount);

        // 3. التعديل الجديد: زيادة الخزنة الورقية للمكتب (سوري أو دولار)
        $isSyrian = ($currencyType === 'syp_sham_cash');
        $officeSafeField = $isSyrian ? 'balance_sy' : 'balance';
        
        $officeSafe = OfficeSafe::where('office_id', $user->office_id)->lockForUpdate()->first();
        if (!$officeSafe) {
            return response()->json(['message' => 'خزنة المكتب غير موجودة'], 404);
        }

        // << هنا تتم زيادة الخزنة الورقية بأصل المبلغ + عمولة المكتب >>
        $officeSafe->increment($officeSafeField, $totalToDeposit);

        // 4. توثيق العملية في السجلات
        ElectronicSafeLog::create([
            'office_id'       => $user->office_id,
            'currency_type'   => $currencyType,
            'action_type'     => 'sell',
            'amount'          => $amount,
            'commission_rate' => $commRate,
            'net_amount'      => $totalToDeposit,
            'profit'          => $profit,
            'note'            => $note ?? "بيع {$currencyType} | سعر: {$exchangeRate} | عمولة: {$commRate}%",
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تمت عملية البيع بنجاح',
            'details' => [
                'currency_type'        => $currencyType,
                'amount_sold'          => $amount,
                'commission'           => round($profit, 4),
                'esafe_balance_after'  => (float) $eSafe->fresh()->$currencyType,
                'office_balance_after' => (float) $officeSafe->fresh()->$officeSafeField, // الرصيد الورقي الجديد
            ],
        ], 200);
    });
}

    /* ═══════════════════════════════════════════════════
       GET /electronic-safe/logs
       سجل العمليات — فلاتر:
         currency_type, action_type, date_from, date_to, per_page
    ═══════════════════════════════════════════════════ */
    public function logs(Request $request)
    {
        $user    = Auth::user();
        $perPage = min((int) $request->query('per_page', 50), 500);

        $logs = ElectronicSafeLog::where('office_id', $user->office_id)
            ->when($request->currency_type, fn($q) => $q->where('currency_type', $request->currency_type))
            ->when($request->action_type,   fn($q) => $q->where('action_type',   $request->action_type))
            ->when($request->date_from,     fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,       fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'count'  => $logs->count(),
            'totals' => [
                'total_buy_profit'  => round($logs->where('action_type', 'buy')->sum('profit'), 4),
                'total_sell_profit' => round($logs->where('action_type', 'sell')->sum('profit'), 4),
                'total_profit'      => round($logs->sum('profit'), 4),
            ],
            'data' => $logs,
        ]);
    }

}
