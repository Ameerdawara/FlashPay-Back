<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Transfer;

class ProfileController extends Controller
{
    /**
     * جلب بيانات الملف الشخصي مع سجل الحوالات
     */
  public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // نبني الاستعلام الأساسي مع جلب بيانات العملة
        $query = Transfer::with(['currency', 'sendCurrency'])->orderBy('created_at', 'desc');

        // إذا كان المستخدم وكيل، نجلب الحوالات الموجهة إليه
        if ($user->role === 'agent') {
            $query->where('sender_id', $user->id);
        } 
        // إذا كان زبون عادي (Customer)، نجلب الحوالات التي أرسلها
        else {
            $query->where('sender_id', $user->id);
        }

        $transferHistory = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'profile' => $user,
                'transfers_history' => $transferHistory
            ]
        ], 200);
    }

    /**
     * تحديث بيانات الملف الشخصي
     */
    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // التحقق من البيانات المرسلة 
        // نستخدم Rule::unique(...)->ignore($user->id) لكي يسمح للمستخدم بحفظ إيميله/هاتفه الحالي دون أن يعطيه خطأ "مستخدم مسبقاً"
        $validated = $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'email'    => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'    => ['sometimes', 'required', 'string', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8', // اختياري، فقط إذا أراد تغييره
        ]);

        // تحديث الحقول إذا تم إرسالها
        if ($request->has('name')) {
            $user->name = $validated['name'];
        }
        
        if ($request->has('email')) {
            $user->email = $validated['email'];
        }
        
        if ($request->has('phone')) {
            $user->phone = $validated['phone'];
        }
        
        // تحديث كلمة المرور فقط إذا تم إرسالها ولم تكن فارغة
        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data' => $user
        ], 200);
    }
}