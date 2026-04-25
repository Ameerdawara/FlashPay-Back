<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SafeLogController extends Controller
{
    /**
     * GET /safe-logs
     * جلب سجل كل حركات الصناديق للمكتب الحالي
     *
     * Query params:
     *   safe_type   : office_safe | trading | profit_safe | office_main | extra_box | all
     *   action_type : deposit | withdraw | transfer | transfer_to_office | buy | sell | snapshot
     *   date_from   : Y-m-d
     *   date_to     : Y-m-d
     *   per_page    : int (default 200)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'accountant', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $perPage    = (int) $request->query('per_page', 200);
        $safeType   = $request->query('safe_type');   // null = كل الأنواع
        $actionType = $request->query('action_type');
        $dateFrom   = $request->query('date_from');
        $dateTo     = $request->query('date_to');

        try {
            $query = DB::table('safe_action_logs')
                ->select([
                    'id',
                    'office_id',
                    'safe_type',
                    'action_type',
                    'currency',
                    'amount',
                    'description',
                    'performed_by',
                    'balance_after',
                    'balance_sy_after',
                    'notes',
                    'created_at',
                ])
                // ── فلتر المكتب: super_admin يرى الكل ────────────────────
                ->when(
                    $user->role !== 'super_admin',
                    fn($q) => $q->where('office_id', $user->office_id)
                )
                // ── فلتر النوع: إن لم يُرسَل يجلب الكل بما فيه extra_box ─
                ->when(
                    $safeType && $safeType !== 'all',
                    fn($q) => $q->where('safe_type', $safeType)
                )
                // ── فلتر نوع العملية ──────────────────────────────────────
                ->when(
                    $actionType,
                    fn($q) => $q->where('action_type', $actionType)
                )
                // ── فلتر التاريخ ──────────────────────────────────────────
                ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->orderBy('created_at', 'desc')
                ->limit($perPage);

            $logs = $query->get();

            // ── إضافة اسم المنفذ ──────────────────────────────────────────
            $userIds = $logs->pluck('performed_by')->filter()->unique()->values();
            $users   = [];
            if ($userIds->isNotEmpty()) {
                $users = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('name', 'id')
                    ->toArray();
            }

            // ── إضافة اسم الصندوق الإضافي إن كان النوع extra_box ─────────
            $extraBoxIds = $logs
                ->where('safe_type', 'extra_box')
                ->pluck('description') // نستخرج الاسم من الوصف لاحقاً
                ->filter()
                ->unique();

            $logs = $logs->map(function ($log) use ($users) {
                $log->performed_by_name = $users[$log->performed_by] ?? '—';

                // ── تسمية نوع الصندوق بشكل مقروء ────────────────────────
                $log->safe_type_label = match ($log->safe_type) {
                    'office_safe'  => 'خزنة المكتب',
                    'extra_box'    => 'صندوق إضافي',
                    'trading'      => 'صندوق التداول',
                    'office_main'  => 'الصندوق الرئيسي',
                    'profit_safe'  => 'صندوق الأرباح',
                    default        => $log->safe_type,
                };

                // ── تسمية نوع العملية بشكل مقروء ────────────────────────
                $log->action_type_label = match ($log->action_type) {
                    'deposit'            => 'إيداع',
                    'withdraw'           => 'سحب',
                    'transfer'           => 'تحويل',
                    'transfer_to_office' => 'تحويل إلى الخزنة',
                    'buy'                => 'شراء',
                    'sell'               => 'بيع',
                    'snapshot'           => 'لقطة رصيد',
                    default              => $log->action_type,
                };

                return $log;
            });

            return response()->json([
                'status' => 'success',
                'count'  => $logs->count(),
                'data'   => $logs,
            ]);

        } catch (\Exception $e) {
            // جدول safe_action_logs غير موجود بعد
            if (
                str_contains($e->getMessage(), "doesn't exist") ||
                str_contains($e->getMessage(), 'Base table') ||
                str_contains($e->getMessage(), 'relation') // PostgreSQL
            ) {
                return response()->json([
                    'status' => 'success',
                    'count'  => 0,
                    'data'   => [],
                    'note'   => 'جدول السجل لم يُنشأ بعد',
                ]);
            }

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
