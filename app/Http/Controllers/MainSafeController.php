<?php

namespace App\Http\Controllers;
use App\Models\Office;
use App\Models\User;
use App\Models\TradingSafe;
use Illuminate\Http\Request;

class MainSafeController extends Controller
{
    public function agentSafe(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'agent') {
        return response()->json(['message' => 'غير مصرح'], 403);
    }

    $safe = $user->mainSafe;

    if (!$safe) {
        return response()->json(['status' => 'success', 'data' => ['balance' => 0]]);
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'balance' => $safe->balance,
        ]
    ]);
}
    public function index()
    {
        $result = [];

        /*
        |--------------------------------
        | 1️⃣ صناديق المكاتب الرئيسية
        |--------------------------------
        */
        $offices = Office::with('mainSafe')->get();

        foreach ($offices as $office) {
            if (!$office->mainSafe) continue;

            $result[] = [
                'type'      => 'office_main',
                'office_id' => $office->id, // 👈 أضفنا هذا السطر
                'owner'     => $office->name,
                'currency'  => 'USD',
                'balance'   => $office->mainSafe->balance,
                'cost'      => null,
            ];
        }

        /*
        |--------------------------------
        | 2️⃣ صناديق المناديب
        |--------------------------------
        */
        $agents = User::where('role', 'agent')
            ->with('mainSafe')
            ->get();

        foreach ($agents as $agent) {
            if (!$agent->mainSafe) continue;

            $result[] = [
                'type'      => 'agent_main',
                'office_id' => null, // 👈 الوكيل ليس له مكتب، نتركه Null
                'owner'     => $agent->name,
                'currency'  => 'USD',
                'balance'   => $agent->mainSafe->balance,
                'cost'      => null,
            ];
        }

        /*
        |--------------------------------
        | 3️⃣ صناديق التداول (المبيعات)
        |--------------------------------
        */
        $tradingSafes = TradingSafe::with(['office','currency'])->get();

        foreach ($tradingSafes as $safe) {
            $result[] = [
                'type'        => 'trading',
                'office_id'   => $safe->office_id,
                'currency_id' => $safe->currency_id, // أضف هذا السطر ضروري جداً
                'owner'       => $safe->office->name ?? '-',
                'currency'    => $safe->currency->code ?? '',
                'balance'     => $safe->balance,
                'cost'        => $safe->cost,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $result
        ]);
    }
}
