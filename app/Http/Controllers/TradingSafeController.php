<?php

namespace App\Http\Controllers;

use App\Models\TradingSafe;
use App\Models\TradingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TradingSafeController extends Controller
{
    private function authorizeTrading($requestedOfficeId)
    {
        $user = Auth::user();

        // 1. التحقق من الدور (Role)
        $allowedRoles = ['admin', 'acounter', 'cashier'];
        if (!in_array($user->role, $allowedRoles)) {
            abort(403, 'غير مسموح لك بالقيام بعمليات التداول.');
        }

        // 2. التحقق من التبعية للمكتب (حصراً من مكتبه)
        // نفترض أن جدول المستخدمين يحتوي على office_id
        if ($user->office_id != $requestedOfficeId) {
            abort(403, 'لا يمكنك التداول لصندوق مكتب آخر.');
        }
    }

    public function buy(Request $request)
    {
        $validated = $request->validate([
            'office_id'   => 'required|exists:offices,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount'      => 'required|numeric|min:0.01',
            'buy_price'   => 'required|numeric|min:0.01',
        ]);

        // تنفيذ قيود الحماية
        $this->authorizeTrading($validated['office_id']);

        return DB::transaction(function () use ($validated) {
            $safe = TradingSafe::where('office_id', $validated['office_id'])
                ->where('currency_id', $validated['currency_id'])
                ->lockForUpdate() // حماية من التعديل المتزامن
                ->firstOrFail();

            $currentBalance = $safe->balance;
            $currentCost = $safe->cost;
            $newAmount = $validated['amount'];
            $newPrice = $validated['buy_price'];

            // تحديث متوسط التكلفة
            $totalAmount = $currentBalance + $newAmount;
            $newCost = (($currentBalance * $currentCost) + ($newAmount * $newPrice)) / $totalAmount;

            $safe->update([
                'balance' => $totalAmount,
                'cost'    => $newCost
            ]);
            TradingTransaction::create([
                'office_id'   => $validated['office_id'],
                'currency_id' => $validated['currency_id'],
                'user_id' => Auth::id(),
                'type'        => 'buy',
                'amount'      => $newAmount,
                'price'       => $newPrice,
                'cost_at_time' => $newCost, // التكلفة الجديدة بعد الشراء
                'profit'      => 0,
                'transaction_date' => now()->toDateString(),
            ]);

            return response()->json(['status' => 'success', 'data' => $safe]);
        });
    }

    public function sell(Request $request)
    {
        $validated = $request->validate([
            'office_id'   => 'required|exists:offices,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount'      => 'required|numeric|min:0.01',
            'sell_price'  => 'required|numeric|min:0.01',
        ]);

        // تنفيذ قيود الحماية
        $this->authorizeTrading($validated['office_id']);

        return DB::transaction(function () use ($validated) {
            $safe = TradingSafe::where('office_id', $validated['office_id'])
                ->where('currency_id', $validated['currency_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($safe->balance < $validated['amount']) {
                return response()->json(['message' => 'الرصيد غير كافٍ في صندوق التداول'], 400);
            }

            // حساب الربح المحقق بناءً على متوسط التكلفة
            $profit = ($validated['sell_price'] - $safe->cost) * $validated['amount'];

            $safe->decrement('balance', $validated['amount']);

            TradingTransaction::create([
                'office_id'   => $validated['office_id'],
                'currency_id' => $validated['currency_id'],
                'user_id' => Auth::id(),
                'type'        => 'sell',
                'amount'      => $validated['amount'],
                'price'       => $validated['sell_price'],
                'cost_at_time' => $safe->cost, // التكلفة التي كانت موجودة وقت البيع
                'profit'      => $profit,      // تسجيل الربح هنا
                'transaction_date' => now()->toDateString(),
            ]);
            return response()->json([
                'status' => 'success',
                'profit' => $profit,
                'remaining_balance' => $safe->balance
            ]);
        });
    }
    public function dailyReport(Request $request)
    {
        // 1. التأكد من أن المستخدم مسجل دخول
        if (!Auth::check()) {
            return response()->json(['message' => 'غير مصرح لك'], 401);
        }

        $date = $request->query('date', now()->toDateString());

        // 2. جلب معرف المكتب من المستخدم الحالي بطريقة آمنة
        $officeId = Auth::user()->office_id;

        $report = TradingTransaction::where('office_id', $officeId)
            ->where('transaction_date', $date)
            ->selectRaw('
            SUM(CASE WHEN type = "buy" THEN amount ELSE 0 END) as total_bought,
            SUM(CASE WHEN type = "sell" THEN amount ELSE 0 END) as total_sold,
            SUM(profit) as total_net_profit
        ')
            ->first();

        return response()->json([
            'date' => $date,
            'report' => $report
        ]);
    }
}
