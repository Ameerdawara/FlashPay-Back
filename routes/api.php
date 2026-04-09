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
use Illuminate\Http\Request;
use App\Http\Controllers\MonthlyClosingController;

use Illuminate\Support\Facades\Route;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// مسارات العرض فقط (يمكن جعلها عامة أو محمية حسب رغبتك)
Route::get('/countries', [CountryController::class, 'index']);
Route::get('/cities', [CityController::class, 'index']);
Route::get('/currencies', [CurrencyController::class,'index']);


/*
|--------------------------------------------------------------------------
| Protected Routes (المسارات المحمية - تحتاج Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'user' => $request->user()
    ]);
});
Route::middleware('auth:sanctum')->group(function () {

Route::get('/super-safe', [\App\Http\Controllers\SuperSafeController::class, 'show']);
Route::get('/super-safe/logs', [\App\Http\Controllers\SuperSafeController::class, 'logs']);
Route::post('/super-safe/adjust', [\App\Http\Controllers\SuperSafeController::class, 'adjust']);
Route::post('/super-safe/transfer-to-office', [\App\Http\Controllers\SuperSafeController::class, 'transferToOffice']);
Route::post('/super-safe/transfer-from-office', [\App\Http\Controllers\SuperSafeController::class, 'transferFromOffice']);
Route::post('/safes/profit/adjust', [ProfitSafeController::class, 'adjustProfit']);
Route::get('/safe-logs', [SafeLogController::class, 'index']);

// سجل حركات الصناديق (للأدمن والمحاسب)
Route::get('/safe-logs', [\App\Http\Controllers\SafeLogController::class, 'index']);

Route::post('/safes/transfer-profit', [ProfitSafeController::class, 'transferProfitToOffice']);
Route::post('/agent/transfers', [\App\Http\Controllers\TransferController::class, 'storeAgentTransfer'])->middleware('auth:sanctum');

    // جلب قائمة الموظفين (Users)
    Route::post('/safes/adjust', [SafeActionController::class, 'adjust']);
    Route::post('/safes/transfer', [SafeActionController::class, 'transfer']);
    Route::post('/safes/transfer-to-office', [SafeActionController::class, 'transferToOfficeSafe']);
    Route::post('/offices/{officeId}/safe', [OfficeSafeController::class, 'updateBalance']);
    Route::post('/currencies/{id}/rates', [CurrencyController::class, 'updateRates']);
    Route::post('/currencies/get-rate', [CurrencyController::class, 'getRate']);
    Route::apiResource('extra-boxes', App\Http\Controllers\ExtraBoxController::class);
    Route::get('/users', function () {
        // نستخدم with('office') لجلب بيانات المكتب المرتبط لكي لا يحدث خطأ في الواجهة الأمامية

        $users = User::with(['city','country','office'])->get();
        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    });
    Route::get('/safes', [MainSafeController::class, 'index']);

    // الموظف العادي يرى المكاتب فقط (index)
    Route::get('/offices', [OfficeController::class, 'index']);

    // العمليات الحساسة فقط للآدمن
    Route::middleware('can:manage-offices')->group(function () {
        Route::post('/offices', [OfficeController::class, 'store']);
        Route::put('/offices/{office}', [OfficeController::class, 'update']);
        Route::delete('/offices/{office}', [OfficeController::class, 'destroy']);

    });
    Route::put('/users/{id}', function (Request $request, $id) {
        if ($request->user()->role !== 'super_admin') return response()->json(['message' => 'غير مصرح لك'], 403);

        $user = App\Models\User::findOrFail($id);
        $user->update($request->all());
        if ($request->has('password')) {
            $user->password = Illuminate\Support\Facades\Hash::make($request->password);
            $user->save();
        }
        return response()->json(['status' => 'success', 'data' => $user]);
    });

    Route::delete('/users/{id}', function (Request $request, $id) {
        if ($request->user()->role !== 'super_admin') return response()->json(['message' => 'غير مصرح لك'], 403);

        App\Models\User::destroy($id);
        return response()->json(['status' => 'success']);
    });
    // 1. المستخدم والحساب
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/users/{id}/toggle-status', [AuthController::class, 'toggleStatus']);
    Route::get('/trading/report', [TradingSafeController::class, 'dailyReport']);
    Route::get('/trading/report/details', [TradingSafeController::class, 'detailedReport']);
    Route::post('/trading-safe/update-cost', [TradingSafeController::class, 'updateCostManual']);
    // 2. إدارة المكاتب والدول والمدن (صلاحيات كاملة)
    Route::apiResource('offices', OfficeController::class);
    Route::apiResource('countries', CountryController::class)->except(['index']);
    Route::apiResource('cities', CityController::class)->except(['index']);
Route::get('/agent/safe', [MainSafeController::class, 'agentSafe']);
    // مسارات الملف الشخصي وسجل الحوالات
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::put('/profile/update', [ProfileController::class, 'update']);


    Route::get('/agents', [\App\Http\Controllers\AuthController::class, 'getAgents']);

    // 3. العملات

    Route::put('/currencies/update-price/{identifier}', [CurrencyController::class, 'updatePrice']);
    Route::put('/currencies/update-main-price/{identifier}', [CurrencyController::class, 'updateMainPrice']);
Route::get('/main-safes', [MainSafeController::class, 'index']);
    // 4. الحوالات (Transfers)
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::patch('/transfers/{id}/update-status', [TransferController::class, 'update']);
    Route::put('/transfers/{id}/edit', [TransferController::class, 'editTransfer']);
    // ملاحظة: مسار history/all يجب أن يكون قبل {id}/history تجنباً للتعارض
    Route::get('/transfers/history/all', [TransferController::class, 'transferHistory']);
    Route::get('/transfers/{id}/history', [TransferController::class, 'transferHistory']);
    Route::put('/transfers/{id}/edit', [TransferController::class, 'editTransfer']);
    Route::get('/transfers/history/all', [TransferController::class, 'transferHistory']);
    Route::get('/transfers/{id}/history', [TransferController::class, 'transferHistory']);
      Route::post('/safes/transfer-profit', [ProfitSafeController::class, 'transferProfitToOffice']);
    Route::get('/profit-safe', [ProfitSafeController::class, 'getProfitSafe']);
    Route::prefix('trading')->group(function () {
        Route::post('/buy', [TradingSafeController::class, 'buy']);
        Route::post('/sell', [TradingSafeController::class, 'sell']);
    });
    Route::middleware('auth:sanctum')->group(function () {

    // الوكيل: إنشاء طلب + عرض طلباته
    // super_admin: عرض جميع الطلبات
    Route::get('/bank-transfers', [BankTransferController::class, 'index']);
    Route::post('/bank-transfers', [BankTransferController::class, 'store']);
    Route::get('/bank-transfers/{id}', [BankTransferController::class, 'show']);
    Route::get('/agent/safe', [\App\Http\Controllers\TransferController::class, 'agentSafeDetails']);

// إنشاء حوالة المندوب
Route::post('/agent/transfers', [\App\Http\Controllers\TransferController::class, 'storeAgentTransfer']);

// تحديث نسبة ربح مندوب معين (super_admin فقط)
Route::patch('/agent/profit-ratio', [\App\Http\Controllers\TransferController::class, 'updateAgentProfitRatio']);

    // super_admin فقط
    Route::patch('/bank-transfers/{id}/approve', [BankTransferController::class, 'approve']);
    Route::patch('/bank-transfers/{id}/reject', [BankTransferController::class, 'reject']);
});
Route::middleware('auth:sanctum')->group(function () {
    // ... your other routes ...

    // 1. Get archived transfers (Must be placed BEFORE any dynamic {id} routes if you add them later)
    Route::get('/monthly-closing/archived-transfers', [MonthlyClosingController::class, 'archivedTransfers']);

    // 2. Get the list of previous closings
    Route::get('/monthly-closing', [MonthlyClosingController::class, 'index']);

    // 3. Execute a new monthly closing
    Route::post('/monthly-closing', [MonthlyClosingController::class, 'store']);

    // 4. Get safe snapshots for a specific closing
    Route::get('/monthly-closing/{id}/safes', [MonthlyClosingController::class, 'safeSnapshots']);
});
Route::prefix('monthly-closing')->group(function () {

    // GET  /monthly-closing           — سجل الإقفالات (سوبر أدمن + محاسب + أدمن)
    Route::get('/',         [MonthlyClosingController::class, 'index']);

    // POST /monthly-closing           — تنفيذ إقفال جديد (سوبر أدمن فقط)
    Route::post('/',        [MonthlyClosingController::class, 'store']);

    // GET  /monthly-closing/{id}/safes — لقطة الصناديق لإقفال معين
    Route::get('/{id}/safes', [MonthlyClosingController::class, 'safeSnapshots']);

    // GET  /monthly-closing/archived  — الحوالات المؤرشفة (للمراجعة)
    Route::get('/archived', [MonthlyClosingController::class, 'archivedTransfers']);
});
    // المحادثات
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'startConversation']);

    // الرسائل
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::patch('/conversations/{id}/read', [MessageController::class, 'markAsRead']);

    Route::get('/transfers/{id}/messages', [ChatController::class, 'getMessages']);
    Route::post('/transfers/{id}/messages', [ChatController::class, 'sendMessage']);

    // الحوالات الداخلية
    Route::get('/internal-transfers', [InternalTransferController::class, 'index']);
    Route::post('/internal-transfers', [InternalTransferController::class, 'store']);
    Route::patch('/internal-transfers/{id}/toggle-paid', [InternalTransferController::class, 'togglePaidStatus']);
});
