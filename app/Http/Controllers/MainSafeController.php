<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\User;
use App\Models\TradingSafe;
use App\Models\OfficeSafe; // تأكد من استدعاء الموديل
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainSafeController extends Controller
{
    
    public function index()
{
    try {
        $result = [];

        // 1. جلب OfficeSafes (الخزنة التي طلبتها مؤخراً)
        // نتحقق من وجود الموديل أولاً لتجنب خطأ 500
        if (class_exists('App\Models\OfficeSafe')) {
            $officeSafes = \App\Models\OfficeSafe::with('office')->get();
            foreach ($officeSafes as $safe) {
                $result[] = [
                    'type'      => 'office_safe',
                    'office_id' => $safe->office_id,
                    'owner'     => $safe->office->name ?? 'المكتب الرئيسي',
                    'balance'   => (float)$safe->balance,
                    'currency'  => 'USD'
                ];
            }
        }

        // 2. جلب MainSafes (الصناديق الفرعية)
        if (class_exists('App\Models\MainSafe')) {
            $mainSafes = \App\Models\MainSafe::with('owner')->get();
            foreach ($mainSafes as $ms) {
                $result[] = [
                    'type'      => 'office_main',
                    'office_id' => $ms->owner_id,
                    'owner'     => $ms->owner->name ?? 'صندوق فرعي',
                    'balance'   => (float)$ms->balance,
                    'currency'  => 'USD'
                ];
            }
        }

        // 3. جلب TradingSafes (صناديق التداول)
        if (class_exists('App\Models\TradingSafe')) {
            $tradingSafes = \App\Models\TradingSafe::with(['office', 'currency'])->get();
            foreach ($tradingSafes as $ts) {
                $result[] = [
                    'type'      => 'trading',
                    'office_id' => $ts->office_id,
                    'owner'     => $ts->office->name ?? '-',
                    'balance'   => (float)$ts->balance,
                    'currency'  => $ts->currency->code ?? 'USD'
                ];
            }
        }

        return response()->json(['status' => 'success', 'data' => $result]);

    } catch (\Exception $e) {
        // إذا حدث خطأ، سنرسل رسالة واضحة بدلاً من صفحة فارغة
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
            'data' => [
                'balance' => $safe ? $safe->balance : 0,
            ]
        ]);
    }
}