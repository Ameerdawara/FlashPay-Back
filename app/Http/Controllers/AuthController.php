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
//    public function index()
//     {
//         $users = User::with(['office'])->get(); // جلب المكاتب مع معلومات مدنها
//         return response()->json([
//             'status' => 'success',
//             'data' => $users
//         ], 200);
//     }
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|unique:users',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'cashier', 'agent', 'customer'])],

            // شرط الوكيل: يجب أن يكون له دولة ومدينة ورصيد افتتاحي
            'country_id' => 'required_if:role,agent|exists:countries,id',
            'city_id'    => 'required_if:role,agent|exists:cities,id',
            'balance'    => 'required_if:role,agent|numeric|min:0', // الرصيد مطلوب للوكيل فقط

            // شرط الموظفين: المكتب إجباري
            'office_id'  => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['admin', 'accountant', 'cashier']);
                }),
                'exists:offices,id'
            ],
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);

                // تصفير المكتب في حال كان الوكيل (Agent)
                if ($request->role === 'agent') {
                    $data['office_id'] = null;
                }

                // 1. إنشاء المستخدم
                $user = User::create($data);

                // 2. إنشاء الصندوق في حال كان المستخدم وكيل (Agent)
                if ($user->role === 'agent') {
                    $user->mainSafe()->create([
                        'balance' => $request->balance,
                    ]);
                }

                return $user;
            });

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'User (Agent) and Safe created successfully',
                'data' => $user->load('mainSafe'), // تحميل بيانات الصندوق في الرد
                'access_token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تسجيل الدخول
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid login details'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        // حذف التوكن الحالي للمستخدم
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }
}
