<?php

namespace App\Http\Controllers;

use App\Models\OfficeSafe;
use App\Models\TradingSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OfficeSafeController extends Controller
{
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

    /**
     * POST /offices/{officeId}/safe
     * body: { amount, type: deposit|withdraw, currency: usd|sy }
     *
     * إيداع SYP → office_safe.balance_sy += X  &&  trading_safe.balance_sy += X
     * سحب   SYP → office_safe.balance_sy -= X  &&  trading_safe.balance_sy -= X
     * USD        → office_safe.balance فقط
     */
    public function updateBalance(Request $request, $officeId)
    {
        $validated = $request->validate([
            'amount'   => 'required|numeric|min:0.01',
            'type'     => 'required|in:deposit,withdraw',
            'currency' => 'sometimes|in:usd,sy',
            'notes'    => 'nullable|string|max:500',
        ]);

        $amount    = abs($validated['amount']);
        $currency  = $validated['currency'] ?? 'usd';
        $isDeposit = $validated['type'] === 'deposit';
        $notes     = $validated['notes'] ?? null;
         $user = $request->user();

        return DB::transaction(function () use ($officeId, $amount, $currency, $isDeposit, $notes, $user) {

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

                $this->logAction([
                    'office_id'        => $officeId,
                    'safe_type'        => 'office_safe',
                    'action_type'      => $isDeposit ? 'deposit' : 'withdraw',
                    'currency'         => 'USD',
                    'amount'           => $amount,
                    'description'      => ($isDeposit ? 'إيداع دولار' : 'سحب دولار') . ' في خزنة المكتب'
                                       . ($notes ? " — {$notes}" : ''),
                    'performed_by'     => $user?->id,
                    'balance_after'    => $officeSafe->fresh()->balance,
                    'balance_sy_after' => $officeSafe->fresh()->balance_sy,
                    'notes'            => $notes,
                ]);

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

            $isDeposit
                ? $officeSafe->increment('balance_sy', $amount)
                : $officeSafe->decrement('balance_sy', $amount);

            $tradingSafe = TradingSafe::where('office_id', $officeId)
                ->where('currency_id', 1)
                ->lockForUpdate()->first();

            $this->logAction([
                'office_id'        => $officeId,
                'safe_type'        => 'office_safe',
                'action_type'      => $isDeposit ? 'deposit' : 'withdraw',
                'currency'         => 'SYP',
                'amount'           => $amount,
                'description'      => ($isDeposit ? 'إيداع ليرة سورية' : 'سحب ليرة سورية') . ' في خزنة المكتب'
                                   . ($notes ? " — {$notes}" : ''),
                'performed_by'     => $user->id ?? null,
                'balance_after'    => $officeSafe->fresh()->balance,
                'balance_sy_after' => $officeSafe->fresh()->balance_sy,
            ]);

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
