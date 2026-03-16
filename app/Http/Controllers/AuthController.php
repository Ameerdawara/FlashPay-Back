<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|unique:users',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'cashier', 'agent', 'customer'])],

            'id_card_image' => 'required_if:role,customer|image|mimes:jpeg,png,jpg|max:2048',
            // التعديل هنا: جعلنا الـ ID مطلوباً فقط إذا لم يتم إرسال الاسم!
            'country_id' => 'required_without:country_name|exists:countries,id|nullable',
            'city_id'    => 'required_without:city_name|exists:cities,id|nullable',

            'balance'    => 'required_if:role,agent|numeric|min:0',

            'office_id'  => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['admin', 'accountant', 'cashier']);
                }),
                'exists:offices,id',
                'nullable'
            ],
            'country_name' => 'nullable|string',
            'city_name'    => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);
                if ($request->hasFile('id_card_image')) {
                    // إنشاء اسم فريد للصورة
                    $imageName = time() . '_' . uniqid() . '.' . $request->id_card_image->extension();
                    // نقل الصورة لمجلد public/uploads/id_cards
                    $request->id_card_image->move(public_path('uploads/id_cards'), $imageName);
                    // تخزين المسار في المصفوفة ليتم حفظه في الداتابيز
                    $data['id_card_image'] = 'uploads/id_cards/' . $imageName;
                }
                if ($request->role === 'agent') {
                    $data['office_id'] = null;
                }

                if (!empty($data['city_name'])) {
                    $city = \App\Models\City::where('name', $data['city_name'])->first();
                    if ($city) $data['city_id'] = $city->id;
                    unset($data['city_name']);
                }

                if (!empty($data['country_name'])) {
                    $country = \App\Models\Country::where('name', $data['country_name'])->first();
                    if ($country) $data['country_id'] = $country->id;
                    unset($data['country_name']);
                }

                $user = clone User::create($data); // استخدام clone لضمان كائن نظيف

                if ($user->role === 'agent') {
                    $user->mainSafe()->create([
                        'balance' => $request->balance ?? 0,
                    ]);
                }

                return $user;
            });

            // إخبار المحرر بوجود الـ Token لإخفاء الخط الأحمر
            /** @var \App\Models\User $user */
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user,
                'access_token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // التعديل الأهم: دمج فحص حالة الحظر (is_active) أثناء تسجيل الدخول
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password, 'is_active' => 1])) {

            // التحقق لمعرفة هل الخطأ في الباسورد أم أنه محظور فعلاً؟
            $userExists = User::where('email', $request->email)->first();
            if ($userExists && !$userExists->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'عذراً، هذا الحساب محظور من قبل الإدارة.'
                ], 403);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'بيانات الدخول خاطئة'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function getAgents(Request $request)
    {
        $user = $request->user();

        // التعديل هنا: جلب الوكلاء الفعالين فقط (غير المحظورين)
        $query = User::where('role', 'agent')->where('is_active', 1)->select('id', 'name', 'phone');

        if ($user->city_id) {
            $query->where('city_id', $user->city_id);
        }

        $agents = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $agents
        ], 200);
    }

    public function toggleStatus($id)
    {
        // نجلب المستخدم الحالي عن طريق الأيدي لتجنب الخطأ الأحمر
        $currentAdmin = \App\Models\User::find(Auth::id());

        // نتحقق من الدور
        if (!$currentAdmin || $currentAdmin->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $user = \App\Models\User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'تفعيل' : 'حظر';
        return response()->json(['message' => "تم $status المستخدم بنجاح"]);
    }
}
