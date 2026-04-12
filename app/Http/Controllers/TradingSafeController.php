<?php

namespace App\Http\Controllers;

use App\Models\TradingSafe;
use App\Models\OfficeSafe;
use App\Models\ProfitSafe;
use App\Models\TradingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TradingSafeController extends Controller
{
    private function authorizeTrading($requestedOfficeId)
    {
        $user = Auth::user();
        $allowedRoles = ['admin', 'acounter', 'cashier'];
        if (!in_array($user->role, $allowedRoles)) {
            abort(403, 'غير مسموح لك بالقيام بعمليات التداول.');
        }
        if ($user->office_id != $requestedOfficeId) {
            abort(403, 'لا يمكنك التداول لصندوق مكتب آخر.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // شراء
    // - يتحقق أن trading_safe.balance_sy يكفي (amount × buy_price)
    // - ينقص من trading_safe.balance_sy
    // - يزيد trading_safe.balance (الدولار)
    // - يحدّث متوسط التكلفة AVCO
    // ─────────────────────────────────────────────────────────────────────
    public function buy(Request $request)
    {
        $validated = $request->validate([
            'office_id'   => 'required|exists:offices,id',
            'currency_id' => 'exists:currencies,id',
            'amount'      => 'required|numeric|min:0.01',
            'buy_price'   => 'required|numeric|min:0.01',
        ]);

        $this->authorizeTrading($validated['office_id']);

        return DB::transaction(function () use ($validated) {
            $safe = TradingSafe::where('office_id', $validated['office_id'])
                ->where('currency_id', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $costSy = $validated['amount'] * $validated['buy_price'];
$officeSafe = OfficeSafe::where('office_id', $validated['office_id'])
                ->lockForUpdate()->firstOrFail();
            // ── التحقق من رصيد الليرة السورية ─────────────────────────────
            if ($officeSafe->balance_sy < $costSy) {
                return response()->json([
                    'message' => 'رصيد الليرة السورية غير كافٍ للشراء! '
                               . 'المطلوب: ' . number_format($costSy, 2)
                               . ' | المتاح: ' . number_format($safe->balance_sy, 2),
                ], 400);
            }

            $officeSafe->decrement('balance_sy', $costSy);

            // ── تحديث متوسط التكلفة AVCO ──────────────────────────────────
            $totalUsd = $safe->balance + $validated['amount'];
            $newCost  = (($safe->balance * $safe->cost) + ($validated['amount'] * $validated['buy_price']))
                        / $totalUsd;

            // ── تحديث السيف ───────────────────────────────────────────────
            $safe->update([
                'balance'    => $totalUsd,
                'cost'       => $newCost,
                 // نقص الليرات المدفوعة
            ]);

            TradingTransaction::create([
                'office_id'        => $validated['office_id'],
                'currency_id'      => 1,
                'user_id'          => Auth::id(),
                'type'             => 'buy',
                'amount'           => $validated['amount'],
                'price'            => $validated['buy_price'],
                'cost_at_time'     => $newCost,
                'profit'           => 0,
                'transaction_date' => now()->toDateString(),
            ]);

            return response()->json([
                'status'         => 'success',
                'data'           => $safe->fresh(),
                'balance_sy'     => $officeSafe->fresh()->balance_sy,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // بيع
    // - ينقص trading_safe.balance (الدولار)
    // - trading_safe.balance_sy لا يتغير (البضاعة بيعت، الليرات جاءت من العميل)
    // - office_safe.balance_sy += قيمة البيع الكاملة (sell_price × amount)
    // - profit_safe.profit_trade += الربح فقط (sell_price - cost) × amount
    // ─────────────────────────────────────────────────────────────────────
    public function sell(Request $request)
    {
        $validated = $request->validate([
            'office_id'   => 'required|exists:offices,id',
            'currency_id' => 'exists:currencies,id',
            'amount'      => 'required|numeric|min:0.01',
            'sell_price'  => 'required|numeric|min:0.01',
        ]);

        $this->authorizeTrading($validated['office_id']);

        return DB::transaction(function () use ($validated) {
            $safe = TradingSafe::where('office_id', $validated['office_id'])
                ->where('currency_id', 1)
                ->lockForUpdate()
                ->firstOrFail();

            if ($safe->balance < $validated['amount']) {
                return response()->json(['message' => 'رصيد الدولار غير كافٍ في صندوق التداول'], 400);
            }

            $costAtTime  = $safe->cost;
            $sellAmount  = $validated['amount'];
            $sellPrice   = $validated['sell_price'];

            // الربح = (سعر البيع - متوسط التكلفة) × الكمية
            $profit      = ($sellPrice - $costAtTime) * $sellAmount;
            // القيمة الكاملة التي استلمناها من العميل بالليرة
            $sellValueSy = $sellAmount * $sellPrice;
            $newBalance  = $safe->balance - $sellAmount;

            // ── 1. تحديث trading_safe ─────────────────────────────────────
            //    balance_sy لا يتغير هنا — الليرات الواردة تذهب لـ office_safe
           $safe->update([
           'balance'    => $newBalance,
           'cost'       => $newBalance == 0 ? 0 : $costAtTime,
]);
            // ── 2. office_safe.balance_sy += قيمة البيع كاملة ────────────
            $officeSafe = OfficeSafe::where('office_id', $validated['office_id'])
                ->lockForUpdate()->firstOrFail();
            $officeSafe->increment('balance_sy', $sellValueSy-$profit);

            // ── 3. profit_safe.profit_trade += الربح فقط ─────────────────
            $profitSafe = ProfitSafe::firstOrCreate(
                ['office_id' => $validated['office_id']],
                ['profit_trade' => 0, 'profit_main' => 0]
            );
            $profitSafe->increment('profit_trade', $profit);

            TradingTransaction::create([
                'office_id'        => $validated['office_id'],
                'currency_id'      => 1,
                'user_id'          => Auth::id(),
                'type'             => 'sell',
                'amount'           => $sellAmount,
                'price'            => $sellPrice,
                'cost_at_time'     => $costAtTime,
                'profit'           => $profit,
                'transaction_date' => now()->toDateString(),
            ]);

            return response()->json([
                'status'             => 'success',
                'profit'             => $profit,
                'remaining_balance'  => $safe->fresh()->balance,
                'office_balance_sy'  => $officeSafe->fresh()->balance_sy,

            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // تقرير يومي مختصر
    // ─────────────────────────────────────────────────────────────────────
    public function dailyReport(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'غير مصرح لك'], 401);
        }

        $date     = $request->query('date', now()->toDateString());
        $officeId = Auth::user()->office_id;

        $report = TradingTransaction::where('office_id', $officeId)
            ->where('transaction_date', $date)
            ->selectRaw('
                SUM(CASE WHEN type = "buy"  THEN amount ELSE 0 END) as total_bought,
                SUM(CASE WHEN type = "sell" THEN amount ELSE 0 END) as total_sold,
                SUM(profit) as total_net_profit
            ')
            ->first();

        return response()->json(['date' => $date, 'report' => $report]);
    }


    public function updateCostManual(Request $request)
{
    $validated = $request->validate([
        'office_id' => 'required|exists:offices,id',
        'cost'      => 'required|numeric|min:0',
    ]);

    // يمكنك إضافة تحقق هنا إذا كان الشخص super_admin فقط هو من يعدل

    $safe = TradingSafe::where('office_id', $validated['office_id'])
                ->where('currency_id', 1) // نعدل تكلفة الدولار مقابل الليرة
                ->firstOrFail();

    $safe->update(['cost' => $validated['cost']]);

    return response()->json([
        'status' => 'success',
        'message' => 'تم تحديث التكلفة يدوياً',
        'new_cost' => $safe->cost
    ]);
}

    // ─────────────────────────────────────────────────────────────────────
    // تقرير تفصيلي
    // ─────────────────────────────────────────────────────────────────────
    public function detailedReport(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'غير مصرح لك'], 401);
        }

        $user = Auth::user();

        if ($user->role === 'super_admin') {
            $officeId = $request->query('office_id');
            if (!$officeId) {
                return response()->json(['message' => 'يرجى تحديد المكتب'], 422);
            }
        } else {
            $officeId = $user->office_id;
            if (!$officeId) {
                return response()->json(['message' => 'لم يتم تعيينك لأي مكتب'], 403);
            }
        }

        $date = $request->query('date', now()->toDateString());

        $transactions = TradingTransaction::with(['currency', 'user'])
            ->where('office_id', $officeId)
            ->where('transaction_date', $date)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'total_bought'     => $transactions->where('type', 'buy')->sum('amount'),
            'total_sold'       => $transactions->where('type', 'sell')->sum('amount'),
            'total_net_profit' => $transactions->sum('profit'),
        ];

        return response()->json([
            'status'       => 'success',
            'date'         => $date,
            'office_id'    => $officeId,
            'summary'      => $summary,
            'transactions' => $transactions,
        ]);
    }
}
