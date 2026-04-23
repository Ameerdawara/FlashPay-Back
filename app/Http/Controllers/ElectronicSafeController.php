<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\OfficeSafe;
use App\Models\ElectronicSafe;
use App\Models\ElectronicSafeLog;

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

            // تكلفة الشراء بالدولار + العمولة
            $usdBase          = $this->calcUsdCost($currencyType, $amount, $exchangeRate);
            $commission       = ($usdBase * $commRate) / 100;
            $totalUsdDeducted = $usdBase + $commission;

            // خزنة المكتب
            $officeSafe = OfficeSafe::where('office_id', $user->office_id)
                ->lockForUpdate()->first();

            if (!$officeSafe) {
                return response()->json(['message' => 'خزنة المكتب غير موجودة'], 404);
            }

            if ($officeSafe->balance < $totalUsdDeducted) {
                return response()->json([
                    'message' => 'رصيد الدولار في الخزنة غير كافٍ — المطلوب: $' . number_format($totalUsdDeducted, 4),
                ], 400);
            }

            $officeSafe->decrement('balance', $totalUsdDeducted);

            $eSafe = ElectronicSafe::firstOrCreate(['office_id' => $user->office_id]);
            $eSafe->increment($currencyType, $amount);

            ElectronicSafeLog::create([
                'office_id'       => $user->office_id,
                'currency_type'   => $currencyType,
                'action_type'     => 'buy',
                'amount'          => $amount,
                'commission_rate' => $commRate,
                'net_amount'      => $totalUsdDeducted,
                'profit'          => $commission,
                'note'            => $note ?? "شراء {$currencyType} | سعر: {$exchangeRate} | عمولة: {$commRate}%",
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تمت عملية الشراء بنجاح',
                'details' => [
                    'currency_type'        => $currencyType,
                    'amount_bought'        => $amount,
                    'usd_base_cost'        => round($usdBase, 4),
                    'commission_usd'       => round($commission, 4),
                    'total_usd_deducted'   => round($totalUsdDeducted, 4),
                    'office_balance_after' => (float) $officeSafe->fresh()->balance,
                    'esafe_balance_after'  => (float) $eSafe->fresh()->$currencyType,
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

            // التحقق من رصيد الخزنة الإلكترونية
            $eSafe = ElectronicSafe::where('office_id', $user->office_id)
                ->lockForUpdate()->first();

            if (!$eSafe || $eSafe->$currencyType < $amount) {
                return response()->json([
                    'message' => 'رصيد الخزنة الإلكترونية غير كافٍ — المتاح: '
                                 . number_format($eSafe?->$currencyType ?? 0, 2),
                ], 400);
            }

            // المبلغ المُضاف بالدولار
            $usdGross    = $this->calcUsdReceived($currencyType, $amount, $exchangeRate);
            $commission  = ($usdGross * $commRate) / 100;
            $netUsdAdded = $usdGross - $commission;

            // خزنة المكتب
            $officeSafe = OfficeSafe::where('office_id', $user->office_id)
                ->lockForUpdate()->first();

            if (!$officeSafe) {
                return response()->json(['message' => 'خزنة المكتب غير موجودة'], 404);
            }

            $eSafe->decrement($currencyType, $amount);
            $officeSafe->increment('balance', $netUsdAdded);

            ElectronicSafeLog::create([
                'office_id'       => $user->office_id,
                'currency_type'   => $currencyType,
                'action_type'     => 'sell',
                'amount'          => $amount,
                'commission_rate' => $commRate,
                'net_amount'      => $netUsdAdded,
                'profit'          => $commission,
                'note'            => $note ?? "بيع {$currencyType} | سعر: {$exchangeRate} | عمولة: {$commRate}%",
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تمت عملية البيع بنجاح',
                'details' => [
                    'currency_type'         => $currencyType,
                    'amount_sold'           => $amount,
                    'usd_gross'             => round($usdGross, 4),
                    'commission_usd'        => round($commission, 4),
                    'net_usd_added'         => round($netUsdAdded, 4),
                    'office_balance_after'  => (float) $officeSafe->fresh()->balance,
                    'esafe_balance_after'   => (float) $eSafe->fresh()->$currencyType,
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

    /* ── Helpers ── */
    private function calcUsdCost(string $type, float $amount, float $rate): float
    {
        return match ($type) {
            'syp_sham_cash' => $amount / $rate,   // ليرة → دولار
            'usd_sham_cash' => $amount,            // دولار → دولار
            'usdt'          => $amount * $rate,    // USDT * سعر$/USDT
            default         => $amount,
        };
    }

    private function calcUsdReceived(string $type, float $amount, float $rate): float
    {
        return match ($type) {
            'syp_sham_cash' => $amount / $rate,
            'usd_sham_cash' => $amount,
            'usdt'          => $amount * $rate,
            default         => $amount,
        };
    }
}
