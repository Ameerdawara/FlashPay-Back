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

            'country_id' => 'required_if:role,agent|exists:countries,id',
            'city_id'    => 'required_if:role,agent|exists:cities,id',
            'balance'    => 'required_if:role,agent|numeric|min:0',

            'office_id'  => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['admin', 'accountant', 'cashier']);
                }),
                'exists:offices,id'
            ],
            // استقبال الأسماء من تطبيق فلاتر
            'country_name' => 'nullable|string',
            'city_name'    => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);

                if ($request->role === 'agent') {
                    $data['office_id'] = null;
                }

                // حيلة تحويل الأسماء إلى أرقام ID وحفظها للمستخدم
                if (!empty($data['city_name'])) {
                    $city = \App\Models\City::where('name', $data['city_name'])->first();
                    if ($city) $data['city_id'] = $city->id;
                    unset($data['city_name']); // إزالتها من المصفوفة حتى لا تسبب خطأ في قاعدة البيانات
                }
                if (!empty($data['country_name'])) {
                    $country = \App\Models\Country::where('name', $data['country_name'])->first();
                    if ($country) $data['country_id'] = $country->id;
                    unset($data['country_name']);
                }

                // 1. إنشاء المستخدم
                $user = User::create($data);

                // 2. إنشاء الصندوق للوكيل
                if ($user->role === 'agent') {
                    $user->mainSafe()->create([
                        'balance' => $request->balance ?? 0,
                    ]);
                }

                return $user;
            });

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
    /**
     * جلب قائمة الوكلاء
     */
   public function getAgents(Request $request)
    {
        $user = $request->user();
        
        $query = User::where('role', 'agent')->select('id', 'name', 'phone');

        // فلترة الوكلاء بناءً على مدينة الزبون (إذا كانت دبي، يجلب وكلاء دبي فقط)
        if ($user->city_id) {
            $query->where('city_id', $user->city_id);
        }

        $agents = $query->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $agents
        ], 200);
    }
}
