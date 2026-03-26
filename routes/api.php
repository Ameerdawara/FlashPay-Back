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
use App\Http\Controllers\TradingSafeController; // لا تنسى استدعاء الكونترولر الجديد
use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'user' => $request->user()
    ]);
});
Route::middleware('auth:sanctum')->group(function () {
    // جلب قائمة الموظفين (Users)
    Route::post('/safes/adjust', [SafeActionController::class, 'adjust']);
    Route::post('/safes/transfer', [SafeActionController::class, 'transfer']);
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

    Route::prefix('trading')->group(function () {
        Route::post('/buy', [TradingSafeController::class, 'buy']);
        Route::post('/sell', [TradingSafeController::class, 'sell']);
    });
    // المحادثات
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations/start', [ConversationController::class, 'startConversation']);

    // الرسائل
    Route::post('/messages/send', [MessageController::class, 'sendMessage']);
    Route::patch('/conversations/{id}/read', [MessageController::class, 'markAsRead']);

    Route::get('/transfers/{id}/messages', [ChatController::class, 'getMessages']);
    Route::post('/transfers/{id}/messages', [ChatController::class, 'sendMessage']);
});
