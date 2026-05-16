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

    /**
     * GET /agents/safes  (super_admin only)
     * يُرجع صناديق جميع المندوبين مع أرباحهم ونسبهم
     */
    public function agentsSafes(Request $request)
    {
        try {
            $agents = \App\Models\User::where('role', 'agent')
                ->with(['mainSafe', 'city', 'country'])
                ->get();

            $result = $agents->map(function ($agent) {
                $safe = $agent->mainSafe;

                $profitRatio = 0.0;
                if ($safe && $safe->agent_profit_ratio > 0) {
                    $profitRatio = (float) $safe->agent_profit_ratio;
                } elseif ($agent->agent_profit_ratio > 0) {
                    $profitRatio = (float) $agent->agent_profit_ratio;
                }

                return [
                    'agent_id'           => $agent->id,
                    'agent_name'         => $agent->name,
                    'agent_phone'        => $agent->phone,
                    'city'               => $agent->city->name ?? '—',
                    'country'            => $agent->country->name ?? '—',
                    'balance'            => $safe ? (float) $safe->balance : 0.0,
                    'agent_profit'       => $safe ? (float) ($safe->agent_profit ?? 0) : 0.0,
                    'agent_profit_ratio' => $profitRatio,
                    'safe_id'            => $safe?->id,
                ];
            });

            return response()->json(['status' => 'success', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /agents/{id}/withdraw-profit  (super_admin only)
     * سحب أرباح مندوب معين من صندوقه
     */
    public function withdrawAgentProfit(Request $request, $agentId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $agent = \App\Models\User::where('id', $agentId)->where('role', 'agent')->firstOrFail();
        $safe  = $agent->mainSafe;

        if (!$safe) {
            return response()->json(['status' => 'error', 'message' => 'لا يوجد صندوق لهذا المندوب'], 404);
        }

        $amount = (float) $request->amount;

        if ($safe->agent_profit < $amount) {
            return response()->json(['status' => 'error', 'message' => 'الأرباح غير كافية للسحب'], 400);
        }

        $safe->decrement('agent_profit', $amount);

        return response()->json([
            'status'              => 'success',
            'message'             => "تم سحب $amount من أرباح {$agent->name}",
            'remaining_profit'    => (float) $safe->fresh()->agent_profit,
        ]);
    }

    /**
     * PUT /agents/{id}/profit-ratio  (super_admin only)
     * تعديل نسبة ربح المندوب
     */
    public function updateAgentProfitRatio(Request $request, $agentId)
    {
        $request->validate([
            'agent_profit_ratio' => 'required|numeric|min:0|max:100',
        ]);

        $agent = \App\Models\User::where('id', $agentId)->where('role', 'agent')->firstOrFail();
        $safe  = $agent->mainSafe;

        $ratio = (float) $request->agent_profit_ratio;

        // تحديث في المستخدم
        $agent->update(['agent_profit_ratio' => $ratio]);

        // تحديث في MainSafe إن وجد
        if ($safe) {
            $safe->update(['agent_profit_ratio' => $ratio]);
        }

        return response()->json([
            'status'             => 'success',
            'message'            => "تم تحديث نسبة ربح {$agent->name} إلى {$ratio}%",
            'agent_profit_ratio' => $ratio,
        ]);
    }

    public function agentSafe(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'agent') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $safe = $user->mainSafe;

        // ✅ sender_id هو العمود الصحيح — لا يوجد agent_id في جدول transfers
        $transfers = \App\Models\Transfer::where('sender_id', $user->id)
            ->whereIn('status', ['approved', 'waiting', 'ready', 'completed'])
            ->latest()
            ->get()
            ->toArray();

        // ✅ يقرأ agent_profit_ratio من MainSafe أولاً (المصدر الأصح)
        // ثم من users كـ fallback — لأن العمود قد يكون null في users
        $profitRatio = 0.0;
        if ($safe && $safe->agent_profit_ratio > 0) {
            $profitRatio = (float) $safe->agent_profit_ratio;
        } elseif ($user->agent_profit_ratio > 0) {
            $profitRatio = (float) $user->agent_profit_ratio;
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'balance'            => $safe ? (float) $safe->balance : 0.0,
                'agent_profit'       => $safe ? (float) ($safe->agent_profit ?? 0) : 0.0,
                'agent_profit_ratio' => $profitRatio,
                'transfers'          => $transfers,
            ],
        ]);
    }
}
