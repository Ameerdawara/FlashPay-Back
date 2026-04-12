<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OfficeSafe;
use App\Models\MainSafe;
use App\Models\TradingSafe;
use Illuminate\Support\Facades\DB;

class SafeActionController extends Controller
{
    // تابع التعديل اليدوي (فقط للخزنة المكتبية)
    public function adjust(Request $request)
    {
        $request->validate([
            'office_id' => 'required',
            'amount'    => 'required|numeric',
        ]);

        $safe = OfficeSafe::where('office_id', $request->office_id)->first();

        if (!$safe) {
            return response()->json(['status' => 'error', 'message' => 'الخزنة غير موجودة'], 404);
        }

        if ($request->amount < 0 && $safe->balance < abs($request->amount)) {
            return response()->json(['status' => 'error', 'message' => 'الرصيد غير كافٍ'], 400);
        }

        $safe->increment('balance', $request->amount);

        return response()->json(['status' => 'success', 'new_balance' => $safe->balance]);
    }

    // تحويل من خزنة المكتب → صندوق رئيسي أو تداول
    public function transfer(Request $request)
    {
        $request->validate([
            'office_id' => 'required',
            'to_type'   => 'required|in:office_main,trading',
            'amount'    => 'required|numeric|min:0.01',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $fromSafe = OfficeSafe::where('office_id', $request->office_id)
                    ->lockForUpdate()->firstOrFail();

                if ($fromSafe->balance < $request->amount) {
                    throw new \Exception('رصيد الخزنة الأساسية غير كافٍ');
                }

                if ($request->to_type === 'office_main') {
                    $toSafe = MainSafe::where('owner_id', $request->office_id)
                        ->where('owner_type', 'App\\Models\\Office')->firstOrFail();
                } else {
                    $toSafe = TradingSafe::where('office_id', $request->office_id)
                        ->where('currency_id', 1)->firstOrFail();
                }

                $fromSafe->decrement('balance', $request->amount);
                $toSafe->increment('balance', $request->amount);

                return response()->json(['status' => 'success', 'message' => 'تم التحويل بنجاح']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // تحويل من صندوق رئيسي أو تداول → خزنة المكتب (العكس)
    public function transferToOfficeSafe(Request $request)
    {
        $request->validate([
            'office_id' => 'required',
            'from_type' => 'required|in:office_main,trading',
            'amount'    => 'required|numeric|min:0.01',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // الصندوق المصدر
                if ($request->from_type === 'office_main') {
                    $fromSafe = MainSafe::where('owner_id', $request->office_id)
                        ->where('owner_type', 'App\\Models\\Office')
                        ->lockForUpdate()->firstOrFail();
                } else {
                    $fromSafe = TradingSafe::where('office_id', $request->office_id)
                        ->lockForUpdate()->firstOrFail();
                }

                if ($fromSafe->balance < $request->amount) {
                    throw new \Exception('الرصيد غير كافٍ في الصندوق المصدر');
                }

                // الصندوق الهدف: خزنة المكتب
                $toSafe = OfficeSafe::where('office_id', $request->office_id)
                    ->lockForUpdate()->firstOrFail();

                $fromSafe->decrement('balance', $request->amount);
                $toSafe->increment('balance', $request->amount);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'تم التحويل إلى خزنة المكتب بنجاح',
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
