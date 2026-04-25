<?php

namespace App\Http\Controllers;

use App\Models\ExtraBox;
use App\Models\OfficeSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExtraBoxController extends Controller
{
    public function index()
    {
        return response()->json(['status' => 'success', 'data' => ExtraBox::all()], 200);
    }

    /**
     * POST /extra-boxes
     * body: { name, amount_debit, amount_credit, office_id }
     * amount_debit  = مبلغ "منه"  (رصيد موجود في الصندوق)
     * amount_credit = مبلغ "عليه" (مديونية على الصندوق، يُحفظ سالباً)
     * الرصيد الصافي = amount_debit - amount_credit
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'amount_debit'  => 'nullable|numeric|min:0',
            'amount_credit' => 'nullable|numeric|min:0',
            'office_id'     => 'required|exists:offices,id',
        ]);

        $debit  = (float)($request->amount_debit  ?? 0);
        $credit = (float)($request->amount_credit ?? 0);
        $net    = $debit - $credit;          // يمكن أن يكون سالباً

        $box = ExtraBox::create([
            'name'      => $request->name,
            'amount'    => $net,
            'office_id' => $request->office_id,
        ]);

        // سجّل الإيداع الأولي إن كان منه > 0
        if ($debit > 0) {
            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'deposit',
                'amount'        => $debit,
                'description'   => "إيداع أولي (منه) عند إنشاء صندوق: {$box->name}",
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->amount,
                'notes'         => null,
            ]);
        }
        // سجّل المديونية إن كان عليه > 0
        if ($credit > 0) {
            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'withdraw',
                'amount'        => $credit,
                'description'   => "مديونية أولية (عليه) عند إنشاء صندوق: {$box->name}",
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->amount,
                'notes'         => null,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إنشاء الصندوق بنجاح',
            'data'    => $box,
        ], 201);
    }

    public function show($id)
    {
        $box = ExtraBox::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $box], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'sometimes|required|string|max:255']);
        $box = ExtraBox::findOrFail($id);
        $box->update($request->only(['name']));
        return response()->json(['status' => 'success', 'message' => 'تم التعديل بنجاح', 'data' => $box], 200);
    }

    // ─── POST /extra-boxes/{id}/deposit ──────────────────────────────────
    public function deposit(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                // ✅ PostgreSQL-safe: where + lockForUpdate + firstOrFail
                $box = ExtraBox::where('id', $id)->lockForUpdate()->firstOrFail();
                $box->increment('amount', (float)$request->amount);

                $this->writeLog([
                    'office_id'     => $box->office_id,
                    'action_type'   => 'deposit',
                    'amount'        => (float)$request->amount,
                    'description'   => "إيداع في صندوق إضافي: {$box->name}"
                                     . ($request->notes ? " — {$request->notes}" : ''),
                    'performed_by'  => $request->user()?->id,
                    'balance_after' => $box->fresh()->amount,
                    'notes'         => $request->notes,
                ]);

                return response()->json([
                    'status'      => 'success',
                    'message'     => 'تم الإيداع بنجاح',
                    'new_balance' => $box->fresh()->amount,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // ─── POST /extra-boxes/{id}/withdraw ─────────────────────────────────
    public function withdraw(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $box = ExtraBox::where('id', $id)->lockForUpdate()->firstOrFail();

                // ✅ throw بدلاً من return داخل transaction لمنع خطأ PostgreSQL
                if ($box->amount < (float)$request->amount) {
                    throw new \Exception('الرصيد غير كافٍ في الصندوق');
                }

                $box->decrement('amount', (float)$request->amount);

                $this->writeLog([
                    'office_id'     => $box->office_id,
                    'action_type'   => 'withdraw',
                    'amount'        => (float)$request->amount,
                    'description'   => "سحب من صندوق إضافي: {$box->name}"
                                     . ($request->notes ? " — {$request->notes}" : ''),
                    'performed_by'  => $request->user()?->id,
                    'balance_after' => $box->fresh()->amount,
                    'notes'         => $request->notes,
                ]);

                return response()->json([
                    'status'      => 'success',
                    'message'     => 'تم السحب بنجاح',
                    'new_balance' => $box->fresh()->amount,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // ─── POST /extra-boxes/{id}/transfer-to-office ───────────────────────
    public function transferToOfficeSafe(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $box = ExtraBox::where('id', $id)->lockForUpdate()->firstOrFail();

                // ✅ throw بدلاً من return داخل transaction
                if ($box->amount < (float)$request->amount) {
                    throw new \Exception('الرصيد غير كافٍ في الصندوق');
                }

                $officeSafe = OfficeSafe::where('office_id', $box->office_id)
                    ->lockForUpdate()->firstOrFail();

                $box->decrement('amount', (float)$request->amount);
                $officeSafe->increment('balance', (float)$request->amount);

                // سجل الخروج من extra_box
                $this->writeLog([
                    'office_id'     => $box->office_id,
                    'action_type'   => 'transfer',
                    'amount'        => (float)$request->amount,
                    'description'   => "تحويل من صندوق [{$box->name}] إلى خزنة المكتب — {$request->notes}",
                    'performed_by'  => $request->user()?->id,
                    'balance_after' => $box->fresh()->amount,
                    'notes'         => $request->notes,
                ]);

                // سجل الدخول في office_safe
                $this->writeLog([
                    'office_id'     => $box->office_id,
                    'safe_type'     => 'office_safe',
                    'action_type'   => 'deposit',
                    'amount'        => (float)$request->amount,
                    'description'   => "استلام من صندوق [{$box->name}] — {$request->notes}",
                    'performed_by'  => $request->user()?->id,
                    'balance_after' => $officeSafe->fresh()->balance,
                    'notes'         => $request->notes,
                ]);

                return response()->json([
                    'status'              => 'success',
                    'message'             => 'تم التحويل إلى خزنة المكتب بنجاح',
                    'box_balance'         => $box->fresh()->amount,
                    'office_safe_balance' => $officeSafe->fresh()->balance,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        $box = ExtraBox::findOrFail($id);
        $box->delete();
        return response()->json(['status' => 'success', 'message' => 'تم حذف الصندوق بنجاح'], 200);
    }

    // ─── مساعد: كتابة سجل ────────────────────────────────────────────────
    private function writeLog(array $data): void
    {
        try {
            DB::table('safe_action_logs')->insert(array_merge([
                'safe_type'        => 'extra_box',
                'currency'         => 'USD',
                'balance_sy_after' => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ], $data));
        } catch (\Exception $e) {
            // جدول غير موجود بعد — نتجاهل
        }
    }
}
