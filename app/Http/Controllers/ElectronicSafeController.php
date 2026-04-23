<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\OfficeSafe;      // التعديل هنا
use App\Models\ProfitSafe;
use App\Models\ElectronicSafe;
use App\Models\ElectronicSafeLog;

class ElectronicSafeController extends Controller
{
    public function buy(Request $request)
{
    $request->validate([
        'currency_type'   => 'required|in:syp_sham_cash,usd_sham_cash,usdt',
        'amount'          => 'required|numeric|min:1',
        'commission_rate' => 'required|numeric|min:0',
    ]);

    return DB::transaction(function () use ($request) {
        $user = Auth::user();
        $amount = $request->amount; 
        $rate = $request->commission_rate; 

        // 1. تحديد الحقل المطلوب بناءً على نوع العملة
        $isSyrian = ($request->currency_type === 'syp_sham_cash');
        $balanceField = $isSyrian ? 'balance_sy' : 'balance';

        // 2. حساب المربح والصافي (للتوثيق فقط)
        $profit = ($amount * $rate) / 100; 
        $netAmount = $amount - $profit;    

        // 3. جلب خزنة المكتب
        $officeSafe = OfficeSafe::where('office_id', $user->office_id)->lockForUpdate()->first();

        if (!$officeSafe) {
            return response()->json(['message' => 'خزنة المكتب غير موجودة'], 404);
        }

        // 4. التحقق من الرصيد
        if ($officeSafe->$balanceField < $netAmount) {
            $currencyName = $isSyrian ? 'ليرة سورية' : 'دولار';
            return response()->json(['message' => "رصيد الـ $currencyName في الخزنة غير كافٍ"], 400);
        }

        // 5. الخصم من خزنة المكتب
        $officeSafe->decrement($balanceField, $netAmount);

        // 6. الزيادة في الخزنة الإلكترونية (esafe)
        $eSafe = ElectronicSafe::firstOrCreate(['office_id' => $user->office_id]);
        $eSafe->increment($request->currency_type, $amount);

        $log = ElectronicSafeLog::create([
            'office_id'       => $user->office_id,
            'currency_type'   => $request->currency_type,
            'action_type'     => 'buy',
            'amount'          => $amount,
            'commission_rate' => $rate,
            'net_amount'      => $netAmount,
            'profit'          => $profit, // القيمة موثقة هنا في السجل
            'note'            => "شراء {$request->currency_type} خصماً من رصيد $balanceField"
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تمت العملية بنجاح وتوثيق الربح في السجلات',
            'details' => [
                'deducted_from' => $balanceField,
                'net_deducted'  => $netAmount,
                'esafe_updated' => $request->currency_type,
                'profit_logged' => $profit
            ]
        ], 200);
    });
}
public function sell(Request $request)
{
    $request->validate([
        'currency_type'   => 'required|in:syp_sham_cash,usd_sham_cash,usdt',
        'amount'          => 'required|numeric|min:1',
        'commission_rate' => 'required|numeric|min:0',
    ]);

    return DB::transaction(function () use ($request) {
        $user = Auth::user();
        $amount = $request->amount; // المبلغ الإلكتروني المراد بيعه
        $rate = $request->commission_rate; 

        // 1. تحديد الحقل المستهدف في خزنة المكتب بناءً على العملة
        $isSyrian = ($request->currency_type === 'syp_sham_cash');
        $balanceField = $isSyrian ? 'balance_sy' : 'balance';

        // 2. حساب المربح وإجمالي المبلغ الذي سيستلمه المكتب من الزبون
        $profit = ($amount * $rate) / 100; 
        $totalReceived = $amount + $profit; // المبلغ الواصل للخزنة الورقية

        // 3. التحقق من توفر الرصيد الإلكتروني الكافي للبيع
        $eSafe = ElectronicSafe::where('office_id', $user->office_id)->lockForUpdate()->first();

        if (!$eSafe || $eSafe->{$request->currency_type} < $amount) {
            return response()->json(['message' => 'رصيد العملة الإلكترونية غير كافٍ لإتمام عملية البيع'], 400);
        }

        // 4. خصم الرصيد من الخزنة الإلكترونية (esafe)
        $eSafe->decrement($request->currency_type, $amount);

        // 5. زيادة الرصيد في خزنة المكتب (الرصيد الأصلي + الربح)
        $officeSafe = OfficeSafe::where('office_id', $user->office_id)->lockForUpdate()->first();
        if (!$officeSafe) {
            return response()->json(['message' => 'خزنة المكتب غير موجودة'], 404);
        }
        $officeSafe->increment($balanceField, $totalReceived);

        // 6. توثيق العملية في السجلات مع حفظ قيمة الربح
        $log = ElectronicSafeLog::create([
            'office_id'       => $user->office_id,
            'currency_type'   => $request->currency_type,
            'action_type'     => 'sell',
            'amount'          => $amount,
            'commission_rate' => $rate,
            'net_amount'      => $totalReceived, // المبلغ الإجمالي المضاف للخزنة الورقية
            'profit'          => $profit,        // توثيق الربح فقط دون زيادته في جداول أخرى
            'note'            => "بيع {$request->currency_type} وإيداع المبلغ في رصيد $balanceField"
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تمت عملية البيع بنجاح',
            'details' => [
                'added_to_office' => $totalReceived,
                'deducted_from_esafe' => $amount,
                'profit_documented' => $profit
            ]
        ], 200);
    });
}
}