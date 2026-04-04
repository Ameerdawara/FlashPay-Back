<?php
namespace App\Http\Controllers;

use App\Models\ProfitSafe;
use App\Models\OfficeSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProfitSafeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // تسجيل العملية في safe_action_logs
    // ─────────────────────────────────────────────────────────────────────
    private function logAction(array $data): void
    {
        try {
            DB::table('safe_action_logs')->insert(array_merge([
                'created_at' => now(),
                'updated_at' => now(),
            ], $data));
        } catch (\Exception $e) {
            // الجدول غير موجود بعد — نتجاهل الخطأ
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /safes/profit/adjust
    // body: { office_id, amount, type: deposit|withdraw, currency: usd|sy }
    // إيداع أو سحب يدوي من صندوق الأرباح (admin / super_admin)
    // ─────────────────────────────────────────────────────────────────────
    public function adjustProfit(Request $request)
    {
        $validated = $request->validate([
            'office_id' => 'required|exists:offices,id',
            'amount'    => 'required|numeric|min:0.01',
            'type'      => 'required|in:deposit,withdraw',
            'currency'  => 'required|in:usd,sy',
        ]);

        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['message' => 'غير مصرح لك بهذه العملية'], 403);
        }

        $amount    = abs($validated['amount']);
        $isDeposit = $validated['type'] === 'deposit';
        $currency  = $validated['currency'];

        return DB::transaction(function () use ($validated, $amount, $isDeposit, $currency, $user) {
            $profitSafe = ProfitSafe::where('office_id', $validated['office_id'])
                ->lockForUpdate()
                ->firstOrCreate(
                    ['office_id' => $validated['office_id']],
                    ['profit_trade' => 0, 'profit_main' => 0]
                );

            // USD → profit_main  |  SYP → profit_trade
            $column = ($currency === 'usd') ? 'profit_main' : 'profit_trade';

            if (!$isDeposit && $profitSafe->{$column} < $amount) {
                $label = $currency === 'usd' ? 'الدولار (profit_main)' : 'الليرة (profit_trade)';
                return response()->json([
                    'message' => "رصيد {$label} في صندوق الأرباح غير كافٍ! المتاح: " . number_format($profitSafe->{$column}, 2),
                ], 400);
            }

            $isDeposit
                ? $profitSafe->increment($column, $amount)
                : $profitSafe->decrement($column, $amount);

            $fresh = $profitSafe->fresh();

            $this->logAction([
                'office_id'        => $validated['office_id'],
                'safe_type'        => 'profit_safe',
                'action_type'      => $isDeposit ? 'deposit' : 'withdraw',
                'currency'         => strtoupper($currency === 'sy' ? 'SYP' : 'USD'),
                'amount'           => $amount,
                'description'      => ($isDeposit ? 'إيداع يدوي' : 'سحب يدوي') . ' في صندوق الأرباح ('
                                    . ($currency === 'usd' ? 'دولار - profit_main' : 'ليرة سورية - profit_trade') . ')',
                'performed_by'     => $user->id,
                'balance_after'    => $fresh->profit_main,
                'balance_sy_after' => $fresh->profit_trade,
            ]);

            return response()->json([
                'status'       => 'success',
                'message'      => ($isDeposit ? 'تم الإيداع' : 'تم السحب') . ' بنجاح في صندوق الأرباح',
                'profit_main'  => (float) $fresh->profit_main,
                'profit_trade' => (float) $fresh->profit_trade,
                'field'        => $column,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /safes/transfer-profit
    // تحويل من صندوق الأرباح إلى خزنة المكتب + تسجيل في safe_action_logs
    // ─────────────────────────────────────────────────────────────────────
    public function transferProfitToOffice(Request $request)
    {
        $validated = $request->validate([
            'office_id' => 'required|exists:offices,id',
            'amount'    => 'required|numeric|min:0.01',
            'source'    => 'required|in:trade,main',
        ]);

        $user = Auth::user();

        return DB::transaction(function () use ($validated, $user) {
            $profitSafe = ProfitSafe::where('office_id', $validated['office_id'])->lockForUpdate()->firstOrFail();
            $officeSafe = OfficeSafe::where('office_id', $validated['office_id'])->lockForUpdate()->firstOrFail();

            $amount = $validated['amount'];

            if ($validated['source'] === 'trade') {
                $columnToDeduct    = 'profit_trade';
                $columnToIncrement = 'balance_sy';
                $desc = 'تحويل أرباح تداول (ليرة) → خزنة المكتب (SYP)';
                $logCurrency = 'SYP';
            } else {
                $columnToDeduct    = 'profit_main';
                $columnToIncrement = 'balance';
                $desc = 'تحويل أرباح رئيسية (دولار) → خزنة المكتب (USD)';
                $logCurrency = 'USD';
            }

            if ($profitSafe->{$columnToDeduct} < $amount) {
                return response()->json(['message' => 'مبلغ الأرباح غير كافٍ للتحويل'], 400);
            }

            $profitSafe->decrement($columnToDeduct, $amount);
            $officeSafe->increment($columnToIncrement, $amount);

            $freshProfit = $profitSafe->fresh();

            $this->logAction([
                'office_id'        => $validated['office_id'],
                'safe_type'        => 'profit_safe',
                'action_type'      => 'transfer_to_office',
                'currency'         => $logCurrency,
                'amount'           => $amount,
                'description'      => $desc,
                'performed_by'     => $user->id ?? null,
                'balance_after'    => $freshProfit->profit_main,
                'balance_sy_after' => $freshProfit->profit_trade,
            ]);

            return response()->json([
                'status'           => 'success',
                'message'          => 'تم نقل الأرباح إلى خزنة المكتب بنجاح',
                'remaining_profit' => $freshProfit->{$columnToDeduct},
                'profit_main'      => (float) $freshProfit->profit_main,
                'profit_trade'     => (float) $freshProfit->profit_trade,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /safes/profit
    // ─────────────────────────────────────────────────────────────────────
    public function getProfitSafe(Request $request)
    {
        $officeId = $request->user()->office_id;
        $profit   = ProfitSafe::where('office_id', $officeId)->first();

        return response()->json(['status' => 'success', 'data' => $profit]);
    }
}
