<?php
// ===================================================
// أضف هذا الـ Controller كملف جديد:
// app/Http/Controllers/SafeLogController.php
// ===================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SafeLogController extends Controller
{
    /**
     * GET /safe-logs
     * جلب سجل كل حركات الصناديق (transfer/adjust) للمكتب الحالي
     * المحاسب والأدمن فقط
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'accountant', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        // جلب الـ logs من جدول safe_action_logs إن وُجد
        // أو بناء سجل مؤقت من العمليات المتاحة
        try {
            $logs = DB::table('safe_action_logs')
                ->where(function ($q) use ($user) {
                    if ($user->role !== 'super_admin') {
                        $q->where('office_id', $user->office_id);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->take(300)
                ->get();

            return response()->json(['status' => 'success', 'data' => $logs]);

        } catch (\Exception $e) {
            // الجدول غير موجود بعد — نُرجع مصفوفة فارغة
            return response()->json(['status' => 'success', 'data' => []]);
        }
    }
}
