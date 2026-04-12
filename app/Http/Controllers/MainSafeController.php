<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use App\Models\TradingSafe;
use App\Models\OfficeSafe;
use App\Models\ProfitSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainSafeController extends Controller
{
    /**
     * GET /safes
     * يُرجع كل الصناديق مع الـ balance_sy حيثما يوجد
     */
    public function index()
    {
        try {
            $result = [];

            // ── 1. OfficeSafes (خزنة المكتب) ──────────────────────────────
            if (class_exists('App\Models\OfficeSafe')) {
                $officeSafes = \App\Models\OfficeSafe::with('office')->get();
                foreach ($officeSafes as $safe) {
                    $result[] = [
                        'type'       => 'office_safe',
                        'office_id'  => $safe->office_id,
                        'owner'      => $safe->office->name ?? 'المكتب الرئيسي',
                        'balance'    => (float) $safe->balance,
                        'balance_sy' => (float) $safe->balance_sy,
                        'currency'   => 'USD',
                    ];
                }
            }

            // ── 2. MainSafes (الصندوق الرئيسي) ────────────────────────────
            if (class_exists('App\Models\MainSafe')) {
                $mainSafes = \App\Models\MainSafe::with('owner')->get();
                foreach ($mainSafes as $ms) {
                    $result[] = [
                        'type'      => 'office_main',
                        'office_id' => $ms->owner_id,
                        'owner'     => $ms->owner->name ?? 'صندوق فرعي',
                        'balance'   => (float) $ms->balance,
                        'currency'  => 'USD',
                    ];
                }
            }

            // ── 3. TradingSafes (صناديق التداول) ──────────────────────────
            if (class_exists('App\Models\TradingSafe')) {
                $tradingSafes = \App\Models\TradingSafe::with(['office', 'currency'])->get();
                foreach ($tradingSafes as $ts) {
                    $result[] = [
                        'type'        => 'trading',
                        'office_id'   => $ts->office_id,
                        'currency_id' => $ts->currency_id,
                        'owner'       => $ts->office->name ?? '-',
                        'balance'     => (float) $ts->balance,
                        'balance_sy'  => (float) $ts->balance_sy,
                        'cost'        => (float) $ts->cost,
                        'currency'    => $ts->currency->code ?? 'USD',
                    ];
                }
            }

            // ── 4. ProfitSafes (صناديق الأرباح) ────────────────────────────
            if (class_exists('App\Models\ProfitSafe')) {
                $profitSafes = \App\Models\ProfitSafe::all();
                foreach ($profitSafes as $ps) {
                    $result[] = [
                        'type'         => 'profit_safe',
                        'office_id'    => $ps->office_id,
                        'profit_trade' => (float) $ps->profit_trade,
                        'profit_main'  => (float) $ps->profit_main,
                        'currency'     => 'USD',
                    ];
                }
            }

            return response()->json(['status' => 'success', 'data' => $result]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function agentSafe(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'agent') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $safe = $user->mainSafe;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'balance' => $safe ? $safe->balance : 0,
            ],
        ]);
    }
}
