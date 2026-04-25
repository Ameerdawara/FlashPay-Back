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

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'amount'    => 'required|numeric|min:0',
            'office_id' => 'required|exists:offices,id',
        ]);

        $box = ExtraBox::create($request->only(['name', 'amount', 'office_id']));

        if ($request->amount > 0) {
            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'deposit',
                'amount'        => $box->amount,
                'description'   => "إيداع أولي عند إنشاء صندوق: {$box->name}",
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->amount,
                'notes'         => null,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'تم إنشاء الصندوق بنجاح', 'data' => $box], 201);
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

    // POST /extra-boxes/{id}/deposit
    public function deposit(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $box = ExtraBox::lockForUpdate()->findOrFail($id);
            $box->increment('amount', $request->amount);

            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'deposit',
                'amount'        => $request->amount,
                'description'   => "إيداع في صندوق إضافي: {$box->name}" . ($request->notes ? " — {$request->notes}" : ''),
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->fresh()->amount,
                'notes'         => $request->notes,
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم الإيداع بنجاح', 'new_balance' => $box->fresh()->amount]);
        });
    }

    // POST /extra-boxes/{id}/withdraw
    public function withdraw(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $box = ExtraBox::lockForUpdate()->findOrFail($id);

            if ($box->amount < $request->amount) {
                return response()->json(['status' => 'error', 'message' => 'الرصيد غير كافٍ في الصندوق'], 400);
            }

            $box->decrement('amount', $request->amount);

            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'withdraw',
                'amount'        => $request->amount,
                'description'   => "سحب من صندوق إضافي: {$box->name}" . ($request->notes ? " — {$request->notes}" : ''),
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->fresh()->amount,
                'notes'         => $request->notes,
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم السحب بنجاح', 'new_balance' => $box->fresh()->amount]);
        });
    }

    // POST /extra-boxes/{id}/transfer-to-office  — notes مطلوب
    public function transferToOfficeSafe(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes'  => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $box        = ExtraBox::lockForUpdate()->findOrFail($id);
            $officeSafe = OfficeSafe::where('office_id', $box->office_id)->lockForUpdate()->firstOrFail();

            if ($box->amount < $request->amount) {
                return response()->json(['status' => 'error', 'message' => 'الرصيد غير كافٍ في الصندوق'], 400);
            }

            $box->decrement('amount', $request->amount);
            $officeSafe->increment('balance', $request->amount);

            // سجل حركة الخروج من extra_box
            $this->writeLog([
                'office_id'     => $box->office_id,
                'action_type'   => 'transfer',
                'amount'        => $request->amount,
                'description'   => "تحويل من صندوق [{$box->name}] إلى خزنة المكتب — {$request->notes}",
                'performed_by'  => $request->user()?->id,
                'balance_after' => $box->fresh()->amount,
                'notes'         => $request->notes,
            ]);

            // سجل حركة الدخول في office_safe
            $this->writeLog([
                'office_id'     => $box->office_id,
                'safe_type'     => 'office_safe',
                'action_type'   => 'deposit',
                'amount'        => $request->amount,
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
    }

    public function destroy($id)
    {
        $box = ExtraBox::findOrFail($id);
        $box->delete();
        return response()->json(['status' => 'success', 'message' => 'تم حذف الصندوق بنجاح'], 200);
    }

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
            // الجدول غير موجود بعد
        }
    }
}
