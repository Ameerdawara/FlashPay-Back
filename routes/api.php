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
use App\Http\Controllers\MonthlyClosingController;
use App\Http\Controllers\ExtraBoxController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة - بدون توكن)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/cities',    [CityController::class, 'index']);
Route::get('/currencies',[CurrencyController::class,'index']);



/*
|--------------------------------------------------------------------------
| Protected Routes (تحتاج توكن صحيح)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // ─── 1. مشتركة: أي مستخدم مسجّل ────────────────────────────────────────
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/me', fn(Request $r) => response()->json(['user' => $r->user()]));
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::get('/profile',        [ProfileController::class, 'index']);
    Route::put('/profile/update', [ProfileController::class, 'update']);

    Route::get('/agents', [AuthController::class, 'getAgents']);
   Route::get('/offices', [OfficeController::class, 'index']);
    Route::get('/currencies/get-rate', [CurrencyController::class, 'getRate']);
    Route::post('/currencies/get-rate', [CurrencyController::class, 'getRate']);

    // إنشاء وعرض الحوالات والمراسلات
    Route::get('/transfers',               [TransferController::class, 'index']);
    Route::post('/transfers',              [TransferController::class, 'store']);
    Route::get('/transfers/{id}/messages', [ChatController::class, 'getMessages']);
    Route::post('/transfers/{id}/messages',[ChatController::class, 'sendMessage']);

    Route::get('/conversations',             [ConversationController::class, 'index']);
    Route::post('/conversations/start',      [ConversationController::class, 'startConversation']);
    Route::post('/messages/send',            [MessageController::class, 'sendMessage']);
    Route::patch('/conversations/{id}/read', [MessageController::class, 'markAsRead']);
// ─── حوالات البنك (عرض وإنشاء) ────────────────────────────────────────
    Route::post('/bank-transfer',     [BankTransferController::class, 'store'])->middleware('role:agent');
Route::get('/bank-transfer',      [BankTransferController::class, 'index']);
    Route::get('/bank-transfer/{id}', [BankTransferController::class, 'show']);
  
    // ─── موافقة ورفض الإدارة لحوالات البنك ─────────────────────────────────
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::patch('/bank-transfer/{id}/approve', [BankTransferController::class, 'approve']);
        Route::patch('/bank-transfer/{id}/reject',  [BankTransferController::class, 'reject']);
    });

    // ─── 2. Super Admin فقط ────────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        // المكاتب (إنشاء، تعديل، حذف)
        Route::post('/offices',            [OfficeController::class, 'store']);
        Route::put('/offices/{office}',    [OfficeController::class, 'update']);
        Route::delete('/offices/{office}', [OfficeController::class, 'destroy']);

        // الخزنة العليا
        Route::get('/super-safe',                      [\App\Http\Controllers\SuperSafeController::class, 'show']);
        Route::get('/super-safe/logs',                 [\App\Http\Controllers\SuperSafeController::class, 'logs']);
        Route::post('/super-safe/adjust',              [\App\Http\Controllers\SuperSafeController::class, 'adjust']);
        Route::post('/super-safe/transfer-to-office',  [\App\Http\Controllers\SuperSafeController::class, 'transferToOffice']);
        Route::post('/super-safe/transfer-from-office',[\App\Http\Controllers\SuperSafeController::class,'transferFromOffice']);

        // إدارة المستخدمين (تعديل وحذف يدوي)
        Route::put('/users/{id}', function (Request $request, $id) {
            $user = User::findOrFail($id);
            $user->update($request->except('password'));
            if ($request->has('password')) {
                $user->password = Illuminate\Support\Facades\Hash::make($request->password);
                $user->save();
            }
            return response()->json(['status' => 'success', 'data' => $user]);
        });
        Route::delete('/users/{id}', function ($id) {
            User::destroy($id);
            return response()->json(['status' => 'success']);
        });
        Route::patch('/users/{id}/toggle-status', [AuthController::class, 'toggleStatus']);

        // أسعار الصرف وإعداداتها
        Route::put('/currencies/update-price/{identifier}',      [CurrencyController::class, 'updatePrice']);
        Route::put('/currencies/update-main-price/{identifier}', [CurrencyController::class, 'updateMainPrice']);
        Route::post('/currencies/{id}/rates',                    [CurrencyController::class, 'updateRates']);

        Route::apiResource('countries', CountryController::class)->except(['index']);
        Route::apiResource('cities',    CityController::class)->except(['index']);

        // إدارة الصناديق وحركاتها المعقدة
        Route::post('/offices/{officeId}/safe', [OfficeSafeController::class, 'updateBalance']);
        Route::post('/safes/adjust',            [SafeActionController::class, 'adjust']);
        Route::post('/safes/transfer',          [SafeActionController::class, 'transfer']);
        Route::post('/safes/transfer-to-office',[SafeActionController::class, 'transferToOfficeSafe']);
        Route::post('/safes/profit/adjust',     [ProfitSafeController::class, 'adjustProfit']);
        Route::post('/safes/transfer-profit',   [ProfitSafeController::class, 'transferProfitToOffice']);

        Route::patch('/agent/profit-ratio',      [TransferController::class, 'updateAgentProfitRatio']);
        Route::post('/monthly-closing',          [MonthlyClosingController::class, 'store']);
        Route::post('/trading-safe/update-cost', [TradingSafeController::class, 'updateCostManual']);
// Route::get('/bank-transfer',                [BankTransferController::class, 'index']);
//         Route::get('/bank-transfer/{id}',           [BankTransferController::class, 'show']);

//         // الموافقة والرفض لحوالات البنك
//         Route::patch('/bank-transfer/{id}/approve', [BankTransferController::class, 'approve']);
//         Route::patch('/bank-transfer/{id}/reject',  [BankTransferController::class, 'reject']);

        // الصناديق الإضافية
        Route::apiResource('extra-boxes', ExtraBoxController::class);
    });

 Route::middleware('role:admin')->group(function () {
            Route::post('/trading-safe/update-cost', [TradingSafeController::class, 'updateCostManual']);

     Route::post('/offices/{officeId}/safe', [OfficeSafeController::class, 'updateBalance']);
        Route::post('/safes/adjust',            [SafeActionController::class, 'adjust']);
        Route::post('/safes/transfer',          [SafeActionController::class, 'transfer']);
        Route::post('/safes/transfer-to-office',[SafeActionController::class, 'transferToOfficeSafe']);
        Route::post('/safes/profit/adjust',     [ProfitSafeController::class, 'adjustProfit']);
        Route::post('/safes/transfer-profit',   [ProfitSafeController::class, 'transferProfitToOffice']);
     // الموافقة والرفض لحوالات البنك (admin أيضاً)
//    Route::get('/bank-transfer',                [BankTransferController::class, 'index']);
//         Route::get('/bank-transfer/{id}',           [BankTransferController::class, 'show']);

//      Route::patch('/bank-transfer/{id}/approve', [BankTransferController::class, 'approve']);
//         Route::patch('/bank-transfer/{id}/reject',  [BankTransferController::class, 'reject']);

 });
    // ─── 3. مشتركة (Super Admin + Admin + Accountant + Cashier) ────────────
    Route::middleware('role:super_admin,admin,accountant,cashier')->group(function () {
        // جلب الموظفين والمكاتب للقوائم

        Route::get('/users', function () {
            $users = User::with(['city', 'country', 'office'])->get();
            return response()->json(['status' => 'success', 'data' => $users]);
        });
        Route::post('/monthly-closing',          [MonthlyClosingController::class, 'store']);

        // قراءة بيانات الصناديق
        Route::get('/safes',        [MainSafeController::class, 'index']);
        Route::get('/main-safes',   [MainSafeController::class, 'index']);
        Route::get('/office-safe',  [OfficeSafeController::class, 'index']);
        Route::get('/trading-safe', [TradingSafeController::class, 'index']);
        Route::get('/profit-safe',  [ProfitSafeController::class, 'getProfitSafe']);
         Route::get('/transfers/history/all', [TransferController::class, 'transferHistory']);
    Route::get('/transfers/{id}/history', [TransferController::class, 'transferHistory']);
    Route::put('/transfers/{id}/edit', [TransferController::class, 'editTransfer']);
    Route::get('/transfers/history/all', [TransferController::class, 'transferHistory']);
    Route::get('/transfers/{id}/history', [TransferController::class, 'transferHistory']);

        // التداول
        Route::get('/trading/report',         [TradingSafeController::class, 'dailyReport']);
        Route::get('/trading/report/details', [TradingSafeController::class, 'detailedReport']);
        Route::prefix('trading')->group(function () {
            Route::post('/buy',  [TradingSafeController::class, 'buy']);
            Route::post('/sell', [TradingSafeController::class, 'sell']);
        });

        // الحوالات الداخلية
        Route::get('/internal-transfers',                    [InternalTransferController::class, 'index']);
        Route::post('/internal-transfers',                   [InternalTransferController::class, 'store']);
        Route::patch('/internal-transfers/{id}/toggle-paid', [InternalTransferController::class, 'togglePaidStatus']);
  
        // تعديل حالة الحوالات الأساسية
        Route::patch('/transfers/{id}/update-status', [TransferController::class, 'update']);
    });
// ─── 6. Cashier — حوالات البنك ────────────────────────────────────────
    Route::middleware('role:cashier')->group(function () {

    Route::patch('/bank-transfer/{id}/complete',  [BankTransferController::class, 'complete']);
    });

    // ─── 4. مشتركة (Super Admin + Admin + Accountant) ──────────────────────
    Route::middleware('role:super_admin,admin,accountant')->group(function () {
        Route::get('/safe-logs', [SafeLogController::class, 'index']);

        // التعديل على الحوالات وعرض الأرشيف
        Route::put('/transfers/{id}/edit',          [TransferController::class, 'editTransfer']);
        Route::get('/transfers/history/all',        [TransferController::class, 'transferHistory']);
        Route::get('/transfers/{id}/history',       [TransferController::class, 'transferHistory']);

        // الإقفال الشهري
        Route::get('/monthly-closing',                    [MonthlyClosingController::class, 'index']);
        Route::get('/monthly-closing/archived-transfers', [MonthlyClosingController::class, 'archivedTransfers']);
        Route::get('/monthly-closing/{id}/safes',         [MonthlyClosingController::class, 'safeSnapshots']);
    });


    // ─── 5. Agent (المندوب) فقط ────────────────────────────────────────────
    Route::middleware('role:agent')->group(function () {

    Route::get('/agent/safe',         [MainSafeController::class, 'agentSafe']);
        Route::get('/agent/safe-details', [TransferController::class, 'agentSafeDetails']);
    //    Route::get('/bank-transfer',                [BankTransferController::class, 'index']);
    //     Route::get('/bank-transfer/{id}',           [BankTransferController::class, 'show']);
  
        Route::post('/agent/transfers',   [TransferController::class, 'storeAgentTransfer']);
        Route::post('/bank-transfer',     [BankTransferController::class, 'store']);
    });

});
