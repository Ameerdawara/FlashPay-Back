<?php
namespace App\Http\Controllers;

use App\Models\BankTransfer;
use App\Models\SuperSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankTransferController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = BankTransfer::with(['agent:id,name,phone', 'approvedBy:id,name', 'cashier:id,name'])
                              ->orderBy('created_at', 'desc');

        if ($user->role === 'agent') {
            $query->where('agent_id', $user->id);
        } elseif (!in_array($user->role, ['super_admin', 'admin', 'cashier'])) {
            return response()->json(['message' => 'غير مصرح لك بعرض هذه البيانات'], 403);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->get(),
        ]);
    }

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
            'recipient_name' => 'required|string|max:255', // الحقل الجديد
            'phone'          => 'required|string|max:30',
            'amount'         => 'required|numeric|min:1',
            'notes'          => 'nullable|string|max:1000',
            'destination_country' => 'nullable|string|max:100',
            'destination_city'    => 'nullable|string|max:100',
        ]);

        $transfer = BankTransfer::create([
            'agent_id'       => $user->id,
            'bank_name'      => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'full_name'      => $validated['full_name'],
            'recipient_name' => $validated['recipient_name'],
            'phone'          => $validated['phone'],
            'amount'         => $validated['amount'],
            'notes'          => $validated['notes'] ?? null,
            'destination_country' => $validated['destination_country'] ?? null,
            'destination_city'    => $validated['destination_city'] ?? null,
            'status'         => 'pending',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إرسال طلب التحويل البنكي بنجاح وهو بانتظار موافقة الإدارة',
            'data'    => $transfer->load('agent:id,name'),
        ], 201);
    }

   public function approve(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['message' => 'هذه الصلاحية للإدارة فقط'], 403);
        }

        $request->validate([
            'cashier_id' => 'required|exists:users,id'
        ]);

        // ✅ إضافة: التحقق من الكاشير ومكتبه
        $selectedCashier = \App\Models\User::findOrFail($request->cashier_id);

        if ($selectedCashier->role !== 'cashier') {
            return response()->json(['message' => 'المستخدم المحدد ليس كاشير'], 422);
        }

        // إذا كان المستخدم admin (مدير مكتب)، نمنعه من اختيار كاشير من مكتب آخر
        if ($user->role === 'admin') {
            if ($selectedCashier->office_id !== $user->office_id) {
                return response()->json(['message' => 'لا يمكنك اختيار كاشير من مكتب آخر'], 403);
            }
        }

        $transfer = BankTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن تعديل طلب تمت معالجته مسبقاً',
            ], 422);
        }

        $transfer->cashier_id = $request->cashier_id;

        return DB::transaction(function () use ($transfer, $user, $request) {
            $superSafe = \App\Models\SuperSafe::firstOrCreate([], ['balance' => 0]);
            $superSafe->increment('balance', $transfer->amount);

            $transfer->update([
                'status'       => 'admin_approved',
                'approved_by'  => $user->id,
                'cashier_id'   => $request->cashier_id,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تمت الموافقة، وتم إرسالها للكاشير للتسليم',
                'data'    => $transfer->load(['agent:id,name', 'approvedBy:id,name', 'cashier:id,name']),
            ]);
        });
    }
    public function reject(Request $request, $id)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['message' => 'هذه الصلاحية للإدارة فقط'], 403);
        }

        $transfer = BankTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن تعديل طلب تمت معالجته مسبقاً'], 422);
        }

        $transfer->update([
            'status'      => 'rejected',
            'approved_by' => $user->id,
        ]);

        return response()->json(['status' => 'success', 'message' => 'تم رفض الطلب', 'data' => $transfer]);
    }

    // الدالة الجديدة الخاصة بالكاشير
    public function complete(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'cashier') {
            return response()->json(['message' => 'هذه الصلاحية للكاشير فقط'], 403);
        }

        $transfer = BankTransfer::findOrFail($id);

        if ($transfer->status !== 'admin_approved') {
            return response()->json(['message' => 'يجب موافقة الإدارة على الحوالة أولاً'], 422);
        }

        $transfer->update([
            'status'     => 'completed',
            'cashier_id' => $user->id,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم التسليم بنجاح',
            'data'    => $transfer->load(['agent:id,name', 'approvedBy:id,name', 'cashier:id,name'])
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $transfer = BankTransfer::with(['agent:id,name,phone', 'approvedBy:id,name', 'cashier:id,name'])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $transfer]);
    }
}
