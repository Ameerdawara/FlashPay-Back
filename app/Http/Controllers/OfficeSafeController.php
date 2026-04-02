<?php

namespace App\Http\Controllers;

use App\Models\OfficeSafe;
use App\Models\TradingSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeSafeController extends Controller
{
    /**
     * POST /offices/{officeId}/safe
     * body: { amount, type: deposit|withdraw, currency: usd|sy }
     *
     * منطق الليرة السورية:
     *   إيداع  SYP → office_safe.balance_sy += X  &&  trading_safe.balance_sy += X
     *   سحب    SYP → office_safe.balance_sy -= X  &&  trading_safe.balance_sy -= X
     *   USD        → office_safe.balance فقط
     */
    public function updateBalance(Request $request, $officeId)
    {
        $validated = $request->validate([
            'amount'   => 'required|numeric|min:0.01',
            'type'     => 'required|in:deposit,withdraw',
            'currency' => 'sometimes|in:usd,sy',
        ]);

        $amount    = abs($validated['amount']);
        $currency  = $validated['currency'] ?? 'usd';
        $isDeposit = $validated['type'] === 'deposit';

        return DB::transaction(function () use ($officeId, $amount, $currency, $isDeposit) {

            $officeSafe = OfficeSafe::where('office_id', $officeId)
                ->lockForUpdate()->first();

            if (!$officeSafe) {
                return response()->json(['message' => 'هذا المكتب لا يملك خزنة!'], 404);
            }

            // ── دولار USD ─────────────────────────────────────────────────
            if ($currency === 'usd') {
                if (!$isDeposit && $officeSafe->balance < $amount) {
                    return response()->json(['message' => 'رصيد الدولار في الخزنة غير كافٍ!'], 400);
                }
                $isDeposit
                    ? $officeSafe->increment('balance', $amount)
                    : $officeSafe->decrement('balance', $amount);

                return response()->json([
                    'status'      => 'success',
                    'new_balance' => $officeSafe->fresh()->balance,
                    'field'       => 'balance',
                    'message'     => 'تم تحديث رصيد الدولار بنجاح',
                ]);
            }

            // ── ليرة سورية SYP ────────────────────────────────────────────
            if (!$isDeposit && $officeSafe->balance_sy < $amount) {
                return response()->json(['message' => 'رصيد الليرة السورية في الخزنة غير كافٍ!'], 400);
            }

            // 1. تحديث office_safe.balance_sy
            $isDeposit
                ? $officeSafe->increment('balance_sy', $amount)
                : $officeSafe->decrement('balance_sy', $amount);

            // 2. مرآة فورية على trading_safe.balance_sy
            $tradingSafe = TradingSafe::where('office_id', $officeId)
                ->where('currency_id', 1)
                ->lockForUpdate()->first();

          

            return response()->json([
                'status'             => 'success',
                'new_balance_sy'     => $officeSafe->fresh()->balance_sy,
                'trading_balance_sy' => $tradingSafe ? $tradingSafe->fresh()->balance_sy : 0,
                'field'              => 'balance_sy',
                'message'            => 'تم تحديث رصيد الليرة السورية بنجاح',
            ]);
        });
    }
}
