<?php

namespace App\Http\Controllers;

use App\Models\SuperSafe;
use App\Models\SuperSafeLog;
use App\Models\OfficeSafe;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SuperSafeController extends Controller
{
    /**
     * التحقق أن المستخدم super_admin
     */
    private function authorize()
    {
        if (Auth::user()->role !== 'super_admin') {
            abort(403, 'غير مصرح لك');
        }
    }

    /**
     * GET /super-safe
     * جلب رصيد الصندوق الرئيسي للسوبر أدمن
     */
    public function show()
    {
        $this->authorize();
        $safe = SuperSafe::instance();
        return response()->json(['status' => 'success', 'data' => $safe]);
    }

    /**
     * POST /super-safe/adjust
     * إيداع أو سحب يدوي
     * body: { type: deposit|withdraw, amount, note? }
     */
    public function adjust(Request $request)
    {
        $this->authorize();
        $request->validate([
            'type'   => 'required|in:deposit,withdraw',
            'amount' => 'required|numeric|min:0.01',
            'note'   => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $safe   = SuperSafe::instance()->lockForUpdate()->first() ?? SuperSafe::instance();
            $amount = abs($request->amount);
            $balanceBefore = $safe->balance;

            if ($request->type === 'withdraw' && $safe->balance < $amount) {
                return response()->json(['message' => 'الرصيد غير كافٍ في صندوق السوبر'], 400);
            }

            $request->type === 'deposit'
                ? $safe->increment('balance', $amount)
                : $safe->decrement('balance', $amount);

            SuperSafeLog::create([
                'type'           => $request->type,
                'amount'         => $amount,
                'office_id'      => null,
                'office_name'    => null,
                'note'           => $request->note,
                'balance_before' => $balanceBefore,
                'balance_after'  => $safe->fresh()->balance,
            ]);

            return response()->json([
                'status'      => 'success',
                'new_balance' => $safe->fresh()->balance,
                'message'     => $request->type === 'deposit' ? 'تم الإيداع بنجاح' : 'تم السحب بنجاح',
            ]);
        });
    }

    /**
     * POST /super-safe/transfer-to-office
     * تحويل من صندوق السوبر → office_safe مكتب معين
     * body: { office_id, amount, note? }
     */
    public function transferToOffice(Request $request)
    {
        $this->authorize();
        $request->validate([
            'office_id' => 'required|exists:offices,id',
            'amount'    => 'required|numeric|min:0.01',
            'note'      => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $superSafe  = SuperSafe::instance()->lockForUpdate()->first() ?? SuperSafe::instance();
            $officeSafe = OfficeSafe::where('office_id', $request->office_id)
                ->lockForUpdate()->firstOrFail();
            $office     = Office::findOrFail($request->office_id);
            $amount     = abs($request->amount);
            $balanceBefore = $superSafe->balance;

            if ($superSafe->balance < $amount) {
                return response()->json(['message' => 'رصيد صندوق السوبر غير كافٍ'], 400);
            }

            $superSafe->decrement('balance', $amount);
            $officeSafe->increment('balance', $amount);

            SuperSafeLog::create([
                'type'           => 'transfer_to_office',
                'amount'         => $amount,
                'office_id'      => $office->id,
                'office_name'    => $office->name,
                'note'           => $request->note,
                'balance_before' => $balanceBefore,
                'balance_after'  => $superSafe->fresh()->balance,
            ]);

            return response()->json([
                'status'                => 'success',
                'message'               => "تم تحويل $amount إلى مكتب {$office->name}",
                'super_safe_balance'    => $superSafe->fresh()->balance,
                'office_safe_balance'   => $officeSafe->fresh()->balance,
            ]);
        });
    }

    /**
     * POST /super-safe/transfer-from-office
     * سحب من office_safe مكتب معين → صندوق السوبر
     * body: { office_id, amount, note? }
     */
    public function transferFromOffice(Request $request)
    {
        $this->authorize();
        $request->validate([
            'office_id' => 'required|exists:offices,id',
            'amount'    => 'required|numeric|min:0.01',
            'note'      => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $superSafe  = SuperSafe::instance()->lockForUpdate()->first() ?? SuperSafe::instance();
            $officeSafe = OfficeSafe::where('office_id', $request->office_id)
                ->lockForUpdate()->firstOrFail();
            $office     = Office::findOrFail($request->office_id);
            $amount     = abs($request->amount);
            $balanceBefore = $superSafe->balance;

            if ($officeSafe->balance < $amount) {
                return response()->json(['message' => "رصيد خزنة مكتب {$office->name} غير كافٍ"], 400);
            }

            $officeSafe->decrement('balance', $amount);
            $superSafe->increment('balance', $amount);

            SuperSafeLog::create([
                'type'           => 'transfer_from_office',
                'amount'         => $amount,
                'office_id'      => $office->id,
                'office_name'    => $office->name,
                'note'           => $request->note,
                'balance_before' => $balanceBefore,
                'balance_after'  => $superSafe->fresh()->balance,
            ]);

            return response()->json([
                'status'                => 'success',
                'message'               => "تم استلام $amount من مكتب {$office->name}",
                'super_safe_balance'    => $superSafe->fresh()->balance,
                'office_safe_balance'   => $officeSafe->fresh()->balance,
            ]);
        });
    }

    /**
     * GET /super-safe/logs
     * جلب سجل العمليات
     */
    public function logs(Request $request)
    {
        $this->authorize();
        $logs = SuperSafeLog::with('office')
            ->orderBy('created_at', 'desc')
            ->take(200)
            ->get();

        return response()->json(['status' => 'success', 'data' => $logs]);
    }
}
