<?php

namespace App\Http\Controllers;

use App\Models\ExtraBox;
use Illuminate\Http\Request;

class ExtraBoxController extends Controller
{
    // عرض جميع الصناديق الإضافية
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => ExtraBox::all()
        ], 200);
    }

    // إنشاء صندوق جديد
    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'amount' => 'required|numeric',
            'office_id'=> 'required|exists:offices,id'

        ]);

        $box = ExtraBox::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء الصندوق بنجاح',
            'data' => $box
        ], 201);
    }

    // عرض صندوق محدد
    public function show($id)
    {
        $box = ExtraBox::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $box
        ], 200);
    }

    // تعديل صندوق موجود (تغيير الاسم أو الكمية)
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric',
            'notes'  => 'nullable|string|max:500',
        ]);

        $box = ExtraBox::findOrFail($id);

        // سجّل العملية في safe_action_logs إذا تغيّر المبلغ
        if ($request->has('amount') && $request->amount != $box->amount) {
            $diff = $request->amount - $box->amount;
            try {
                \Illuminate\Support\Facades\DB::table('safe_action_logs')->insert([
                    'office_id'        => $box->office_id,
                    'safe_type'        => 'extra_box',
                    'action_type'      => $diff > 0 ? 'deposit' : 'withdraw',
                    'currency'         => 'USD',
                    'amount'           => abs($diff),
                    'description'      => ($diff > 0 ? 'إيداع في' : 'سحب من') . " صندوق إضافي: {$box->name}"
                                       . ($request->notes ? " — {$request->notes}" : ''),
                    'performed_by' => $request->user()?->id,
                    'balance_after'    => $request->amount,
                    'balance_sy_after' => 0,
                    'notes'            => $request->notes,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            } catch (\Exception $e) {
                // الجدول غير موجود بعد — نتجاهل الخطأ
            }
        }

        $box->update($request->only(['name', 'amount']));

        return response()->json([
            'status' => 'success',
            'message' => 'تم التعديل بنجاح',
            'data' => $box
        ], 200);
    }

    // حذف صندوق
    public function destroy($id)
    {
        $box = ExtraBox::findOrFail($id);
        $box->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الصندوق بنجاح'
        ], 200);
    }
}
