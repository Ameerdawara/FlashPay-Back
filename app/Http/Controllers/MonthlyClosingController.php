<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * MonthlyClosingController
 * ─────────────────────────────────────────────────────────────────────────
 * يتحكم في عملية الإقفال الشهري التي تشمل:
 *   1. أرشفة الحوالات المكتملة (completed → archived) — تختفي من الواجهة لكن تبقى في DB
 *   2. تسجيل snapshot للصناديق (OfficeSafe, TradingSafe, ProfitSafe) في جدول monthly_safe_snapshots
 *
 * المسارات المقترحة في api.php (أضفها داخل middleware auth:sanctum):
 *   POST   /monthly-closing              → إجراء الإقفال (super_admin فقط)
 *   GET    /monthly-closing              → عرض سجل الإقفالات السابقة
 *   GET    /monthly-closing/{id}/safes   → تفاصيل snapshot صناديق إقفال معيّن
 *   GET    /transfers?include_archived=1 → يتضمن الحوالات المؤرشفة (لو احتجت)
 * ─────────────────────────────────────────────────────────────────────────
 *
 * جداول جديدة مطلوبة (أضف migrations لها):
 *
 * ① monthly_closings
 *   id, month (YYYY-MM), office_id (nullable → null = كل المكاتب),
 *   archived_transfers_count, performed_by, notes, created_at, updated_at
 *
 * ② monthly_safe_snapshots
 *   id, closing_id (FK monthly_closings), office_id,
 *   office_safe_usd, office_safe_sy,
 *   trading_safe_usd, trading_safe_sy, trading_safe_cost,
 *   profit_safe_main, profit_safe_trade,
 *   created_at, updated_at
 */
class MonthlyClosingController extends Controller
{
    // ─── صلاحيات ────────────────────────────────────────────────────────
    private function onlySuperAdmin()
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['super_admin', 'accountant'])) {
            abort(403, 'هذه العملية مخصصة للمدير العام أو المحاسب فقط.');
        }
        return $user;
    }

    // ─── GET /monthly-closing ────────────────────────────────────────────
    /** عرض سجل الإقفالات السابقة */
    public function index(Request $request)
    {
        $user = Auth::user();

        // المحاسب والسوبر أدمن يمكنهم الاطلاع
        if (!in_array($user->role, ['super_admin', 'accountant', 'admin'])) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $query = DB::table('monthly_closings')
            ->join('users', 'monthly_closings.performed_by', '=', 'users.id')
            ->select(
                'monthly_closings.*',
                'users.name as performed_by_name'
            )
            ->orderBy('monthly_closings.created_at', 'desc');

        // المحاسب يرى إقفالات مكتبه فقط
        if ($user->role === 'accountant' && $user->office_id) {
            $query->where(function ($q) use ($user) {
                $q->where('monthly_closings.office_id', $user->office_id)
                  ->orWhereNull('monthly_closings.office_id');
            });
        }

        $closings = $query->get();

        return response()->json([
            'status' => 'success',
            'data'   => $closings,
        ]);
    }

    // ─── POST /monthly-closing ───────────────────────────────────────────
    /**
     * إجراء الإقفال الشهري
     *
     * body (JSON):
     *   month      string  "YYYY-MM"   الشهر المراد إقفاله (مثال: "2025-03")
     *   office_id  int|null            null = كل المكاتب
     *   notes      string|null
     */
    public function store(Request $request)
    {
        $performer = $this->onlySuperAdmin();

        $validated = $request->validate([
            'month'     => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'office_id' => 'nullable|exists:offices,id',
            'notes'     => 'nullable|string|max:500',
        ]);

        $month    = $validated['month'];
        $officeId = $validated['office_id'] ?? null;

        // المحاسب مقيد بمكتبه فقط
        if ($performer->role === 'accountant') {
            if (!$performer->office_id) {
                return response()->json(['message' => 'لم يتم تعيينك لاي مكتب'], 403);
            }
            $officeId = $performer->office_id;
        }

        // ── التحقق من عدم تكرار الإقفال لنفس الشهر والمكتب ──────────────
        $exists = DB::table('monthly_closings')
            ->where('month', $month)
            ->where(function ($q) use ($officeId) {
                if ($officeId) {
                    $q->where('office_id', $officeId);
                } else {
                    $q->whereNull('office_id');
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => 'error',
                'message' => "تم إقفال شهر {$month} مسبقاً" . ($officeId ? " للمكتب #{$officeId}" : ' (كل المكاتب)'),
            ], 422);
        }

        return DB::transaction(function () use ($month, $officeId, $validated, $performer) {

            // ── 1. أرشفة الحوالات المكتملة للشهر المحدد ──────────────────
          // ── 1. جلب الحوالات المكتملة للشهر المحدد وحساب المجاميع ──────────────────
            $transfersQuery = DB::table('transfers')
    ->where('status', 'completed')
    ->whereYear('created_at', '=', substr($month, 0, 4))
    ->whereMonth('created_at', '=', substr($month, 5, 2));
            if ($officeId) {
                $transfersQuery->where('destination_office_id', $officeId);
            }

            // حساب العدد والمبالغ والأرباح قبل تغيير الحالة
            $archivedCount  = $transfersQuery->count();
            $totalAmountUsd = $transfersQuery->sum('amount_in_usd');
            $totalProfit    = $transfersQuery->sum('fee');

            // نغيّر status → 'archived' (تختفي من الواجهة العادية وتعتبر مقفلة/محذوفة من اليوميات)
            (clone $transfersQuery)->update([
                'status'     => 'archived',
                'updated_at' => now(),
            ]);

            // ── 2. تسجيل سجل الإقفال مع المبالغ ─────────────────────────────────────
            $closingId = DB::table('monthly_closings')->insertGetId([
                'month'                    => $month,
                'office_id'                => $officeId,
                'archived_transfers_count' => $archivedCount,
                'total_amount_usd'         => $totalAmountUsd, // تمت الإضافة
                'total_profit'             => $totalProfit,    // تمت الإضافة
                'performed_by'             => $performer->id,
                'notes'                    => $validated['notes'] ?? null,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            // ── 3. snapshot الصناديق ──────────────────────────────────────
            $snapshotCount = $this->snapshotSafes($closingId, $officeId);

            return response()->json([
                'status'  => 'success',
                'message' => "تم إقفال شهر {$month} بنجاح",
                'data'    => [
                    'closing_id'        => $closingId,
                    'month'             => $month,
                    'office_id'         => $officeId,
                    'archived_transfers'=> $archivedCount,
                    'snapshots_taken'   => $snapshotCount,
                ],
            ]);
        });
    }

    // ─── GET /monthly-closing/{id}/safes ────────────────────────────────
    /** تفاصيل snapshot الصناديق لإقفال معيّن */
    public function safeSnapshots($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'accountant', 'admin'])) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $closing = DB::table('monthly_closings')->find($id);
        if (!$closing) {
            return response()->json(['message' => 'الإقفال غير موجود'], 404);
        }

        $snapshots = DB::table('monthly_safe_snapshots')
            ->join('offices', 'monthly_safe_snapshots.office_id', '=', 'offices.id')
            ->select('monthly_safe_snapshots.*', 'offices.name as office_name')
            ->where('closing_id', $id)
            ->get();

        return response()->json([
            'status'  => 'success',
            'closing' => $closing,
            'data'    => $snapshots,
        ]);
    }

    // ─── GET /monthly-closing/archived-transfers ─────────────────────────
    /**
     * عرض الحوالات المؤرشفة (لأغراض المراجعة فقط)
     * متاح للسوبر أدمن والمدير
     */
    public function archivedTransfers(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'admin','accountant'])) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'month'     => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'office_id' => 'nullable|exists:offices,id',
        ]);

        $query = \App\Models\Transfer::with(['sender', 'currency', 'sendCurrency', 'destinationOffice'])
            ->where('status', 'archived')
            ->orderBy('created_at', 'desc');

        if (!empty($validated['month'])) {
            $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$validated['month']]);
        }

        if (!empty($validated['office_id'])) {
            $query->where('destination_office_id', $validated['office_id']);
        } elseif ($user->role !== 'super_admin') {
            $query->where('destination_office_id', $user->office_id);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->get(),
        ]);
    }

    // ─── Helper: snapshot الصناديق ──────────────────────────────────────
    /**
     * يأخذ snapshot لجميع الصناديق المرتبطة بالمكتب (أو كل المكاتب)
     * ويخزّنها في monthly_safe_snapshots
     *
     * @return int  عدد المكاتب التي أُخذ snapshot لها
     */
    private function snapshotSafes(int $closingId, ?int $officeId): int
    {
        // جلب المكاتب المعنية
        $officesQuery = DB::table('offices');
        if ($officeId) {
            $officesQuery->where('id', $officeId);
        }
        $offices = $officesQuery->get(['id']);

        $count = 0;

        foreach ($offices as $office) {
            $oid = $office->id;

            // ── office_safe ──────────────────────────────────────────────
            $officeSafe = DB::table('office_safes')->where('office_id', $oid)->first();

            // ── trading_safe (currency_id=1 = دولار مقابل ليرة) ─────────
            $tradingSafe = DB::table('trading_safes')
                ->where('office_id', $oid)
                ->where('currency_id', 1)
                ->first();

            // ── profit_safe ──────────────────────────────────────────────
            $profitSafe = DB::table('profit_safes')->where('office_id', $oid)->first();

            DB::table('monthly_safe_snapshots')->insert([
                'closing_id'         => $closingId,
                'office_id'          => $oid,
                'office_safe_usd'    => $officeSafe?->balance    ?? 0,
                'office_safe_sy'     => $officeSafe?->balance_sy ?? 0,
                'trading_safe_usd'   => $tradingSafe?->balance    ?? 0,
                'trading_safe_sy'    => $tradingSafe?->balance_sy ?? 0,
                'trading_safe_cost'  => $tradingSafe?->cost       ?? 0,
                'profit_safe_main'   => $profitSafe?->profit_main  ?? 0,
                'profit_safe_trade'  => $profitSafe?->profit_trade ?? 0,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $count++;
        }

        return $count;
    }
}
