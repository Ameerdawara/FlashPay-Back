<?php
namespace App\Http\Controllers;

use App\Models\ProfitSafe;
use App\Models\OfficeSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitSafeController extends Controller
{
    public function transferProfitToOffice(Request $request)
    {
        $validated = $request->validate([
            'office_id' => 'required|exists:offices,id',
            'amount'    => 'required|numeric|min:0.01',
            'source'    => 'required|in:trade,main' // تحديد هل سنسحب من أرباح التداول أم الرئيسي
        ]);

        return DB::transaction(function () use ($validated) {
            $profitSafe = ProfitSafe::where('office_id', $validated['office_id'])->lockForUpdate()->firstOrFail();
            $officeSafe = OfficeSafe::where('office_id', $validated['office_id'])->lockForUpdate()->firstOrFail();

            $amount = $validated['amount'];

            // تحديد عمود الخصم من صندوق الأرباح وعمود الإضافة في خزنة المكتب بناءً على المصدر
            if ($validated['source'] === 'trade') {
                $columnToDeduct = 'profit_trade';
                $columnToIncrement = 'balance_sy'; // أرباح التداول تذهب للرصيد السوري
            } else {
                $columnToDeduct = 'profit_main';
                $columnToIncrement = 'balance';    // الأرباح الرئيسية تذهب لرصيد الدولار
            }

            // التحقق من أن الأرباح تكفي
            if ($profitSafe->{$columnToDeduct} < $amount) {
                return response()->json(['message' => 'مبلغ الأرباح غير كافٍ للتحويل'], 400);
            }

            // خصم الأرباح من صندوق الأرباح
            $profitSafe->decrement($columnToDeduct, $amount);

            // إضافة المبلغ كسيولة في خزنة المكتب (OfficeSafe) في العمود المخصص
            $officeSafe->increment($columnToIncrement, $amount);

            return response()->json([
                'status' => 'success',
                'message' => 'تم نقل الأرباح إلى خزنة المكتب بنجاح',
                'remaining_profit' => $profitSafe->{$columnToDeduct}
            ]);
        });
    }

    public function getProfitSafe(Request $request) {
        $officeId = $request->user()->office_id; // أو حسب طريقتك في جلب رقم المكتب
        $profit = \App\Models\ProfitSafe::where('office_id', $officeId)->first();

        return response()->json([
            'status' => 'success',
            'data' => $profit
        ]);
    }
}
