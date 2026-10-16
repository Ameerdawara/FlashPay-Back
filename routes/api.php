<?php
use App\Http\Controllers\MainSafeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SafeActionController;
use App\Http\Controllers\TradingSafeController;
use App\Http\Controllers\OfficeSafeController;
use App\Http\Controllers\InternalTransferController;
use App\Http\Controllers\ProfitSafeController;
use App\Http\Controllers\SafeLogController;
use App\Http\Controllers\BankTransferController;
use App\Http\Controllers\ElectronicSafeController;
use App\Http\Controllers\MonthlyClosingController;
use App\Http\Controllers\ExtraBoxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// ✅ /register الآن للعملاء فقط (مضمّن داخل AuthController::register)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::view('/privacy-policy', 'privacy-policy');
Route::get('/countries',  [CountryController::class, 'index']);
Route::get('/cities',     [CityController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);

// ✅ صور الدردشة — عامة عمداً (وسم <img> لا يرسل Bearer token). الأسماء عشوائية 40 حرفاً.
// المسار لا ينتهي بامتداد صورة (الملف يُمرَّر في ?p=) حتى لا يعترضه nginx كملف ثابت ويُرجع 404
Route::get('/media', function (Request $request) {
    $path = (string) $request->query('p', '');
    $disk = Storage::disk('public');

    abort_unless(
        str_starts_with($path, 'chat_images/')
        && !str_contains($path, '..')
        && $disk->exists($path),
        404
    );

    return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
});

// ✅ استعادة كلمة المرور (رمز تحقق عبر البريد) — عامة لكن محمية بـ throttle
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {


    Route::prefix('electronic-safe')->group(function () {

        Route::get('/balances', [ElectronicSafeController::class, 'getBalances']);
        Route::get('/all-balances', [ElectronicSafeController::class, 'getAllBalances']);

        Route::post('/buy',     [ElectronicSafeController::class, 'buy']);
        Route::post('/sell',    [ElectronicSafeController::class, 'sell']);
        Route::get('/logs',     [ElectronicSafeController::class, 'logs']);
    });
    // ─── 1. مشتركة: أي مستخدم مسجّل ────────────────────────────────────
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/me',   fn(Request $r) => response()->json(['user' => $r->user()]));
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile',        [ProfileController::class, 'index']);
    Route::put('/profile/update', [ProfileController::class, 'update']);

    Route::get('/agents',    [AuthController::class, 'getAgents']);
    Route::get('/offices',   [OfficeController::class, 'index']);
    Route::get('/currencies/get-rate',  [CurrencyController::class, 'getRate']);
    Route::post('/currencies/get-rate', [CurrencyController::class, 'getRate']);

    Route::get('/transfers',                [TransferController::class, 'index']);
    Route::post('/transfers',               [TransferController::class, 'store']);
    Route::get('/transfers/{id}/messages',  [ChatController::class, 'getMessages']);
    Route::post('/transfers/{id}/messages', [ChatController::class, 'sendMessage']);

    Route::get('/conversations',              [ConversationController::class, 'index']);
    Route::post('/conversations/start',       [ConversationController::class, 'startConversation']);
    Route::post('/messages/send',             [MessageController::class, 'sendMessage']);
    Route::patch('/conversations/{id}/read',  [MessageController::class, 'markAsRead']);

    // حوالات البنك (عرض فقط)
    Route::get('/bank-transfer',      [BankTransferController::class, 'index']);
    Route::get('/bank-transfer/{id}', [BankTransferController::class, 'show']);

    // موافقة ورفض حوالات البنك
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::patch('/bank-transfer/{id}/approve', [BankTransferController::class, 'approve']);
        Route::patch('/bank-transfer/{id}/reject',  [BankTransferController::class, 'reject']);
    });

    // ─── 2. Super Admin فقط ──────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/offices',            [OfficeController::class, 'store']);
        Route::put('/offices/{office}',    [OfficeController::class, 'update']);
        Route::delete('/offices/{office}', [OfficeController::class, 'destroy']);

        Route::post('/users/{id}/nullify-transfers', [TransferController::class, 'nullifyUserTransfers']);
        Route::delete('/users/{id}', function ($id) {
            User::destroy($id);
            return response()->json(['status' => 'success']);
        });

        // ✅ إنشاء الموظفين/المندوبين — محمي فعليًا الآن (بدل /register العام)
        Route::post('/staff', [AuthController::class, 'createStaff']);

        // الخزنة العليا
        Route::get('/super-safe',                       [\App\Http\Controllers\SuperSafeController::class, 'show']);
        Route::get('/super-safe/logs',                  [\App\Http\Controllers\SuperSafeController::class, 'logs']);
        Route::post('/super-safe/adjust',               [\App\Http\Controllers\SuperSafeController::class, 'adjust']);
        Route::post('/super-safe/transfer-to-office',   [\App\Http\Controllers\SuperSafeController::class, 'transferToOffice']);
        Route::post('/super-safe/transfer-from-office', [\App\Http\Controllers\SuperSafeController::class, 'transferFromOffice']);

        // صناديق المندوبين (super_admin فقط)
        Route::get('/agents/safes',                       [MainSafeController::class, 'agentsSafes']);
        Route::post('/agents/{id}/withdraw-profit',       [MainSafeController::class, 'withdrawAgentProfit']);
        Route::put('/agents/{id}/profit-ratio',           [MainSafeController::class, 'updateAgentProfitRatio']);

        // إدارة المستخدمين
        Route::put('/users/{id}', function (Request $request, $id) {
            $user = User::findOrFail($id);
            $user->update($request->except('password'));
            if ($request->has('password')) {
                $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
                $user->save();
            }
            return response()->json(['status' => 'success', 'data' => $user]);
        });
        Route::patch('/users/{id}/toggle-status', [AuthController::class, 'toggleStatus']);
        // أسعار الصرف — بالدولار (كما كانت)
        Route::put('/currencies/update-price/{identifier}',      [CurrencyController::class, 'updatePrice']);
        Route::put('/currencies/update-main-price/{identifier}', [CurrencyController::class, 'updateMainPrice']);
        Route::post('/currencies/{id}/rates',                    [CurrencyController::class, 'updateRates']);

        // أسعار الصرف — بالسوري (مستقلة تماماً عن الدولار)
        Route::put('/currencies/update-price-syp/{identifier}',      [CurrencyController::class, 'updatePriceSyp']);
        Route::put('/currencies/update-main-price-syp/{identifier}', [CurrencyController::class, 'updateMainPriceSyp']);
        Route::post('/currencies/{id}/rates-syp',                    [CurrencyController::class, 'updateSypRates']);

        Route::apiResource('countries', CountryController::class)->except(['index']);
        Route::apiResource('cities',    CityController::class)->except(['index']);

        // إدارة الصناديق
        Route::patch('/agent/profit-ratio',       [TransferController::class, 'updateAgentProfitRatio']);
        Route::post('/trading-safe/update-cost',  [TradingSafeController::class, 'updateCostManual']);
    });


    Route::middleware('role:super_admin,admin')->group(function () {
        Route::post('/offices/{officeId}/safe',  [OfficeSafeController::class, 'updateBalance']);
        Route::post('/safes/adjust',             [SafeActionController::class, 'adjust']);
        Route::post('/safes/transfer',           [SafeActionController::class, 'transfer']);
        Route::post('/safes/transfer-to-office', [SafeActionController::class, 'transferToOfficeSafe']);
        Route::post('/safes/profit/adjust',      [ProfitSafeController::class, 'adjustProfit']);
        Route::post('/safes/transfer-profit',    [ProfitSafeController::class, 'transferProfitToOffice']);
    });

    // ─── 3. Admin فقط ────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::post('/trading-safe/update-cost',  [TradingSafeController::class, 'updateCostManual']);
    });

    // ─── 4. مشتركة (Super Admin + Admin + Accountant + Cashier) ──────────
    Route::middleware('role:super_admin,admin,accountant,cashier')->group(function () {
        // ✅ ملفات محمية (هوية / إيصالات / مرفقات الدردشة) — للموظفين فقط ومع Bearer token
        // لا ينتهي المسار بامتداد صورة حتى لا يعترضه nginx
        Route::get('/secure-file', function (Request $request) {
            $path = (string) $request->query('p', '');
            $disk = Storage::disk('public');

            $allowed = ['id_cards/', 'receipts/', 'chat_images/'];
            $ok = collect($allowed)->contains(fn ($d) => str_starts_with($path, $d));

            abort_unless($ok && !str_contains($path, '..') && $disk->exists($path), 404);

            return $disk->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
        });

        Route::get('/users', function () {
            $users = User::with(['city', 'country', 'office'])->get();
            return response()->json(['status' => 'success', 'data' => $users]);
        });

        // قراءة بيانات الصناديق
        Route::get('/safes',        [MainSafeController::class, 'index']);
        Route::get('/main-safes',   [MainSafeController::class, 'index']);
        Route::get('/office-safe',  [OfficeSafeController::class, 'index']);
        Route::get('/trading-safe', [TradingSafeController::class, 'index']);
        Route::get('/profit-safe',  [ProfitSafeController::class, 'getProfitSafe']);

        // سجل الحوالات
        Route::get('/transfers/history/all',   [TransferController::class, 'transferHistory']);
        Route::get('/transfers/{id}/history',  [TransferController::class, 'transferHistory']);
        Route::put('/transfers/{id}/edit',     [TransferController::class, 'editTransfer']);

        // التداول
        Route::get('/trading/report',         [TradingSafeController::class, 'dailyReport']);
        Route::get('/trading/report/details', [TradingSafeController::class, 'detailedReport']);
        Route::prefix('trading')->group(function () {
            Route::post('/buy',  [TradingSafeController::class, 'buy']);
            Route::post('/sell', [TradingSafeController::class, 'sell']);
        });

        // الحوالات الداخلية
        Route::get('/internal-transfers',                     [InternalTransferController::class, 'index']);
        Route::post('/internal-transfers',                    [InternalTransferController::class, 'store']);
        Route::patch('/internal-transfers/{id}/toggle-paid', [InternalTransferController::class, 'togglePaidStatus']);

        // تعديل حالة الحوالات
        Route::patch('/transfers/{id}/update-status', [TransferController::class, 'update']);

        // الصناديق الإضافية
        Route::get('/extra-boxes',                              [ExtraBoxController::class, 'index']);
        Route::post('/extra-boxes',                             [ExtraBoxController::class, 'store']);
        Route::get('/extra-boxes/{id}',                        [ExtraBoxController::class, 'show']);
        Route::put('/extra-boxes/{id}',                        [ExtraBoxController::class, 'update']);
        Route::delete('/extra-boxes/{id}',                     [ExtraBoxController::class, 'destroy']);
        Route::post('/extra-boxes/{id}/deposit',               [ExtraBoxController::class, 'deposit']);
        Route::post('/extra-boxes/{id}/withdraw',              [ExtraBoxController::class, 'withdraw']);
        Route::post('/extra-boxes/{id}/transfer-to-office',    [ExtraBoxController::class, 'transferToOfficeSafe']);
    });

    // ─── 5. Super Admin + Admin + Accountant ─────────────────────────────
    Route::middleware('role:super_admin,admin,accountant')->group(function () {
        Route::get('/safe-logs', [SafeLogController::class, 'index']);
        Route::post('/monthly-closing',           [MonthlyClosingController::class, 'store']);

        Route::get('/monthly-closing',                     [MonthlyClosingController::class, 'index']);
        Route::get('/monthly-closing/archived-transfers',  [MonthlyClosingController::class, 'archivedTransfers']);
        Route::get('/monthly-closing/{id}/safes',          [MonthlyClosingController::class, 'safeSnapshots']);
    });

    // ─── 6. Cashier فقط ──────────────────────────────────────────────────
    Route::middleware('role:cashier')->group(function () {
        Route::patch('/bank-transfer/{id}/complete', [BankTransferController::class, 'complete']);
    });

    // ─── 7. Agent فقط ────────────────────────────────────────────────────
    Route::middleware('role:agent')->group(function () {
        Route::get('/agent/safe',         [MainSafeController::class, 'agentSafe']);
        Route::get('/agent/safe-details', [TransferController::class, 'agentSafeDetails']);
        Route::post('/agent/transfers',   [TransferController::class, 'storeAgentTransfer']);
        Route::post('/bank-transfer',     [BankTransferController::class, 'store']);
    });

});