<?php
// =============================================================================
//  BankTransferController.php
//  المسار: app/Http/Controllers/BankTransferController.php
//
//  الأدوار:
//    • agent       → store()  : إنشاء طلب تحويل بنكي (status = pending)
//    • agent       → index()  : عرض طلباته هو فقط
//    • super_admin → index()  : عرض جميع الطلبات
//    • super_admin → approve(): الموافقة → يُضاف المبلغ إلى super_safe
//    • super_admin → reject() : رفض الطلب
// =============================================================================

namespace App\Http\Controllers;

use App\Models\BankTransfer;
use App\Models\SuperSafe;          // تأكّد من وجود هذا الموديل لديك
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankTransferController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    //  GET /bank-transfers
    //  الوكيل: طلباته فقط | super_admin: الكل
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = BankTransfer::with(['agent:id,name,phone', 'approvedBy:id,name'])
                              ->orderBy('created_at', 'desc');

        // الوكيل يرى طلباته فقط
        if ($user->role === 'agent') {
            $query->where('agent_id', $user->id);
        }
        // super_admin يرى الكل (لا قيد إضافي)
        elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك بعرض هذه البيانات'], 403);
        }

        // فلترة اختيارية بالحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->get(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /bank-transfers
    //  الوكيل ينشئ طلب تحويل بنكي — status = pending تلقائياً
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'agent') {
            return response()->json(['message' => 'هذه الخدمة متاحة للوكلاء فقط'], 403);
        }

        $validated = $request->validate([
            'bank_name'      => 'required|string|max:255',
            'account_number' => 'required|string|max:100',
            'full_name'      => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'amount'         => 'required|numeric|min:1',
            'notes'          => 'nullable|string|max:1000',
        ], [
            'bank_name.required'      => 'اسم البنك مطلوب',
            'account_number.required' => 'رقم الحساب مطلوب',
            'full_name.required'      => 'الاسم الكامل مطلوب',
            'phone.required'          => 'رقم الموبايل مطلوب',
            'amount.required'         => 'المبلغ مطلوب',
            'amount.min'              => 'المبلغ يجب أن يكون أكبر من صفر',
        ]);

        $transfer = BankTransfer::create([
            'agent_id'       => $user->id,
            'bank_name'      => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'full_name'      => $validated['full_name'],
            'phone'          => $validated['phone'],
            'amount'         => $validated['amount'],
            'notes'          => $validated['notes'] ?? null,
            'status'         => 'pending', // دائماً pending عند الإنشاء
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إرسال طلب التحويل البنكي بنجاح وهو بانتظار موافقة المشرف',
            'data'    => $transfer->load('agent:id,name'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PATCH /bank-transfers/{id}/approve
    //  super_admin يوافق → يُضاف المبلغ إلى super_safe
    // ─────────────────────────────────────────────────────────────────────────
    public function approve(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'هذه الصلاحية للمشرف العام فقط'], 403);
        }

        $transfer = BankTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن تعديل طلب تمت معالجته مسبقاً',
            ], 422);
        }

        return DB::transaction(function () use ($transfer, $user) {

            // ── إضافة المبلغ إلى super_safe ──────────────────────────────
            // نفترض وجود سجل واحد في جدول super_safes
            // عدّل اسم الجدول/الموديل حسب بنيتك
            $superSafe = \App\Models\SuperSafe::first();

            if (!$superSafe) {
                // إذا لم يكن موجوداً، أنشئه
                $superSafe = \App\Models\SuperSafe::create(['balance' => 0]);
            }

            $superSafe->increment('balance', $transfer->amount);

            // ── تحديث حالة الطلب ──────────────────────────────────────────
            $transfer->update([
                'status'      => 'approved',
                'approved_by' => $user->id,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تمت الموافقة وتم إضافة المبلغ إلى الصندوق الرئيسي',
                'data'    => $transfer->load(['agent:id,name', 'approvedBy:id,name']),
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PATCH /bank-transfers/{id}/reject
    //  super_admin يرفض الطلب
    // ─────────────────────────────────────────────────────────────────────────
    public function reject(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'هذه الصلاحية للمشرف العام فقط'], 403);
        }

        $transfer = BankTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن تعديل طلب تمت معالجته مسبقاً',
            ], 422);
        }

        $transfer->update([
            'status'      => 'rejected',
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم رفض طلب التحويل البنكي',
            'data'    => $transfer->load(['agent:id,name', 'approvedBy:id,name']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /bank-transfers/{id}
    //  عرض تفاصيل طلب واحد (agent يرى طلبه فقط، super_admin يرى الكل)
    // ─────────────────────────────────────────────────────────────────────────
    public function show($id)
    {
        $user = Auth::user();
        $transfer = BankTransfer::with(['agent:id,name,phone', 'approvedBy:id,name'])->findOrFail($id);

        if ($user->role === 'agent' && $transfer->agent_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح لك بعرض هذا الطلب'], 403);
        }

        if (!in_array($user->role, ['agent', 'super_admin'])) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return response()->json(['status' => 'success', 'data' => $transfer]);
    }
}
