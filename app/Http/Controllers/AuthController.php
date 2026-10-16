<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * ✅ تسجيل عام — للعملاء فقط (customer).
     * هذا هو المسار الذي يستخدمه تطبيق Flutter (الموقع العام للتسجيل).
     * لا يُسمح بإنشاء أي صلاحية أعلى من هنا إطلاقًا.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:30|unique:users',
            'password' => 'required|string|min:8',

            // ✅ مقيّد بـ customer فقط — لا يُؤخذ أي دور آخر من المدخلات
            'role'     => ['required', Rule::in(['customer'])],

            'selfie_with_id' => 'required|image|mimes:jpeg,png,jpg|max:4096',
            'id_card_front'  => 'required|image|mimes:jpeg,png,jpg|max:4096',
            'id_card_back'   => 'required|image|mimes:jpeg,png,jpg|max:4096',

            'country_id'   => ['nullable', 'exists:countries,id'],
            'city_id'      => ['nullable', 'exists:cities,id'],

            'country_name' => [
                Rule::requiredIf(fn () => empty($request->country_id)),
                'nullable', 'string',
            ],
            'city_name' => [
                Rule::requiredIf(fn () => empty($request->city_id)),
                'nullable', 'string',
            ],

            'fcm_token' => 'nullable|string',
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);
                $data['role']     = 'customer'; // ✅ تأكيد إضافي صلب، بغض النظر عمّا أُرسل

                unset($data['selfie_with_id'], $data['id_card_front'], $data['id_card_back']);

                if ($request->hasFile('selfie_with_id')) {
                    $data['selfie_with_id'] = $request->file('selfie_with_id')->store('id_cards/selfies', 'public');
                }
                if ($request->hasFile('id_card_front')) {
                    $data['id_card_front'] = $request->file('id_card_front')->store('id_cards/fronts', 'public');
                }
                if ($request->hasFile('id_card_back')) {
                    $data['id_card_back'] = $request->file('id_card_back')->store('id_cards/backs', 'public');
                }

                if (!empty($data['city_name'])) {
                    $city = \App\Models\City::where('name', $data['city_name'])->first();
                    if (!$city) {
                        throw new \Exception('المدينة غير موجودة: ' . $data['city_name']);
                    }
                    $data['city_id'] = $city->id;
                    unset($data['city_name']);
                }

                if (!empty($data['country_name'])) {
                    $country = \App\Models\Country::where('name', $data['country_name'])->first();
                    if (!$country) {
                        throw new \Exception('الدولة غير موجودة: ' . $data['country_name']);
                    }
                    $data['country_id'] = $country->id;
                    unset($data['country_name']);
                }

                return User::create($data);
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

    /**
     * ✅ إنشاء موظفين/مندوبين — محمي بـ auth:sanctum + role:super_admin في الراوت.
     * يطابق تمامًا منطق إنشاء غير-العملاء الذي كان سابقًا داخل register(),
     * لذا لوحة السوبر أدمن (super.js) تعمل بنفس البيانات تمامًا، فقط عبر
     * مسار محمي فعليًا هذه المرة بدل مسار عام كان يثق بالتوكن دون تحققه.
     */
    public function createStaff(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:30|unique:users',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['super_admin', 'admin', 'accountant', 'cashier', 'agent'])],

            'country_id' => ['nullable', 'exists:countries,id'],
            'city_id'    => ['nullable', 'exists:cities,id'],

            'balance'            => 'required_if:role,agent|numeric|min:0',
            'agent_profit_ratio' => 'nullable|numeric|min:0|max:100',
            'office_id' => [
                Rule::requiredIf(fn () => in_array($request->role, ['admin', 'accountant', 'cashier'])),
                'exists:offices,id',
                'nullable'
            ],
        ]);

        try {
            $user = DB::transaction(function () use ($request, $validated) {
                $data = $validated;
                $data['password'] = Hash::make($request->password);

                if ($request->role === 'agent') {
                    $data['office_id'] = null;
                }

                $agentProfitRatio = (float) ($data['agent_profit_ratio'] ?? 0);
                unset($data['agent_profit_ratio']);

                $user = User::create($data);

                if ($user->role === 'agent') {
                    if ($agentProfitRatio > 0) {
                        try {
                            DB::table('users')->where('id', $user->id)
                                ->update(['agent_profit_ratio' => $agentProfitRatio]);
                            $user->agent_profit_ratio = $agentProfitRatio;
                        } catch (\Exception $e) {
                            // العمود غير موجود بعد — نتجاهل
                        }
                    }
                    $user->mainSafe()->create([
                        'balance'            => $request->balance ?? 0,
                        'agent_profit_ratio' => $agentProfitRatio,
                    ]);
                }

                return $user;
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إضافة الموظف بنجاح',
                'data'    => $user,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'فشل إنشاء الموظف: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'     => 'required|email',
            'password'  => 'required',
            'fcm_token' => 'nullable|string',
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
        $user = Auth::user();

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
            'status'  => 'success',
            'message' => 'تم تحديث الـ FCM Token بنجاح'
        ]);
    }

    // =========================================================================
    //  استعادة كلمة المرور عبر رمز تحقق يُرسل بالبريد
    // =========================================================================

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->email));

        $user = User::where('email', $email)->where('is_active', 1)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            Cache::put("pwd_reset:$email", Hash::make($code), now()->addMinutes(10));
            Cache::forget("pwd_reset_attempts:$email");

            try {
                Mail::raw(
                    "رمز استعادة كلمة المرور في FlashPay: $code\nصالح لمدة 10 دقائق. إذا لم تطلب ذلك فتجاهل الرسالة.",
                    function ($m) use ($user) {
                        $m->to($user->email)->subject('استعادة كلمة المرور - FlashPay');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Reset mail failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'إذا كان البريد مسجلًا فقد تم إرسال رمز التحقق',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $email = strtolower(trim($request->email));

        $attemptsKey = "pwd_reset_attempts:$email";
        if ((int) Cache::get($attemptsKey, 0) >= 5) {
            Cache::forget("pwd_reset:$email");
            return response()->json([
                'status'  => 'error',
                'message' => 'تجاوزت عدد المحاولات المسموحة، يرجى طلب رمز جديد'
            ], 429);
        }

        $hash = Cache::get("pwd_reset:$email");
        if (!$hash || !Hash::check($request->code, $hash)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(10));
            return response()->json([
                'status'  => 'error',
                'message' => 'الرمز غير صحيح أو منتهي الصلاحية'
            ], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $user->tokens()->delete();

        Cache::forget("pwd_reset:$email");
        Cache::forget($attemptsKey);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }
}
