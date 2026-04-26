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

            // ✅ الإصلاح: country_id/city_id اختيارية تماماً لأن الـ customer يرسل الأسماء
            'country_id'   => ['nullable', 'exists:countries,id'],
            'city_id'      => ['nullable', 'exists:cities,id'],

            // ✅ الإصلاح: نتحقق أن أحد الخيارين (ID أو Name) موجود للـ customer/agent
            'country_name' => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['agent', 'customer'])
                        && empty($request->country_id);
                }),
                'nullable', 'string',
            ],
            'city_name' => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['agent', 'customer'])
                        && empty($request->city_id);
                }),
                'nullable', 'string',
            ],

            'balance'            => 'required_if:role,agent|numeric|min:0',
            'agent_profit_ratio' => 'nullable|numeric|min:0|max:100',
            'office_id' => [
                Rule::requiredIf(function () use ($request) {
                    return in_array($request->role, ['admin', 'accountant', 'cashier']);
                }),
                'exists:offices,id',
                'nullable'
            ],
            'fcm_token' => 'nullable|string', // ✅ أضف هذا السطر
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);

                // ✅ رفع صورة الهوية
                unset($data['id_card_image']);
                if ($request->hasFile('id_card_image')) {
                    $data['id_card_image'] = $request->file('id_card_image')
                        ->store('id_cards', 'public');
                }

                if ($request->role === 'agent') {
                    $data['office_id'] = null;
                }

                // ✅ تحويل city_name → city_id
                if (!empty($data['city_name'])) {
                    $city = \App\Models\City::where('name', $data['city_name'])->first();
                    if (!$city) {
                        throw new \Exception('المدينة غير موجودة: ' . $data['city_name']);
                    }
                    $data['city_id'] = $city->id;
                    unset($data['city_name']);
                }

                // ✅ تحويل country_name → country_id
                if (!empty($data['country_name'])) {
                    $country = \App\Models\Country::where('name', $data['country_name'])->first();
                    if (!$country) {
                        throw new \Exception('الدولة غير موجودة: ' . $data['country_name']);
                    }
                    $data['country_id'] = $country->id;
                    unset($data['country_name']);
                }

                $user = User::create($data);

                if ($user->role === 'agent') {
                    // ✅ حفظ نسبة الربح صراحةً لتجاوز أي مشكلة في الـ fillable
                    if (!empty($data['agent_profit_ratio'])) {
                        $user->agent_profit_ratio = (float) $data['agent_profit_ratio'];
                        $user->save();
                    }
                    $user->mainSafe()->create([
                        'balance'            => $request->balance ?? 0,
                        'agent_profit_ratio' => (float) ($data['agent_profit_ratio'] ?? 0),
                    ]);
                }

                return $user;
            });

            /** @var \App\Models\User $user */
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status'       => 'success',
                'message'      => 'User created successfully',
                'data'         => $user,
                'access_token' => $token,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'fcm_token' => 'nullable|string', // ✅ أضف هذا السطر
        ]);

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password, 'is_active' => 1])) {
            $userExists = User::where('email', $request->email)->first();
            if ($userExists && !$userExists->is_active) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'عذراً، هذا الحساب محظور من قبل الإدارة.'
                ], 403);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'بيانات الدخول خاطئة'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user  = Auth::user();
        // ✅ تحديث التوكن إذا تم إرساله مع طلب تسجيل الدخول
    if ($request->has('fcm_token') && !empty($request->fcm_token)) {
        $user->fcm_token = $request->fcm_token;
        $user->save();
    }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'       => 'success',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function getAgents(Request $request)
    {
        $user  = $request->user();
        $query = User::where('role', 'agent')->where('is_active', 1)->select('id', 'name', 'phone');

        if ($user->city_id) {
            $query->where('city_id', $user->city_id);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->get()
        ], 200);
    }

    public function toggleStatus($id)
    {
        $currentAdmin = \App\Models\User::find(Auth::id());

        if (!$currentAdmin || $currentAdmin->role !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح لك'], 403);
        }

        $user            = \App\Models\User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'تفعيل' : 'حظر';
        return response()->json(['message' => "تم $status المستخدم بنجاح"]);
    }
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الـ FCM Token بنجاح'
        ]);
    }
}