<?php
// app/Http/Controllers/SafeLogController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SafeLogController extends Controller
{
    /**
     * GET /safe-logs
     * جلب سجل كل حركات الصناديق (إيداع، سحب، تحويل) للمكتب الحالي
     * يدعم:
     *   - safe_type   : office_safe | trading | profit_safe | office_main
     *   - action_type : deposit | withdraw | transfer_to_office | buy | sell | snapshot
     *   - date_from / date_to للفلترة بالتاريخ
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'accountant', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $perPage  = (int) $request->query('per_page', 200);
        $safeType = $request->query('safe_type');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        try {
            $query = DB::table('safe_action_logs')
                ->select([
                    'id', 'office_id', 'safe_type', 'action_type',
                    'currency', 'amount', 'description',
                    'performed_by', 'balance_after', 'balance_sy_after',
                    'created_at',
                ])
                ->when($user->role !== 'super_admin', fn($q) => $q->where('office_id', $user->office_id))
                ->when($safeType,  fn($q) => $q->where('safe_type', $safeType))
                ->when($dateFrom,  fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,    fn($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->orderBy('created_at', 'desc')
                ->limit($perPage);

            $logs = $query->get();

            // إضافة اسم المنفذ إن أمكن
            $userIds = $logs->pluck('performed_by')->filter()->unique()->values();
            $users   = [];
            if ($userIds->isNotEmpty()) {
                $users = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('name', 'id')
                    ->toArray();
            }

            $logs = $logs->map(function ($log) use ($users) {
                $log->performed_by_name = $users[$log->performed_by] ?? '—';
                return $log;
            });

            return response()->json([
                'status' => 'success',
                'count'  => $logs->count(),
                'data'   => $logs,
            ]);

        } catch (\Exception $e) {
            // جدول safe_action_logs غير موجود بعد
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table')) {
                return response()->json(['status' => 'success', 'count' => 0, 'data' => [], 'note' => 'جدول السجل لم يُنشأ بعد']);
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
