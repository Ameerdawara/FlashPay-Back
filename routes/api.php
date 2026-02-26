<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TradingSafeController; // لا تنسى استدعاء الكونترولر الجديد
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::middleware('auth:sanctum')->group(function () {

    // 1. المستخدم والحساب
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/trading/report', [TradingSafeController::class, 'dailyReport']);
    // 2. إدارة المكاتب والدول والمدن (صلاحيات كاملة)
    Route::apiResource('offices', OfficeController::class);
    Route::apiResource('countries', CountryController::class)->except(['index']);
    Route::get('/cities', [CityController::class, 'index']);
    // 3. العملات
    Route::put('/currencies/update-price/{identifier}', [CurrencyController::class, 'updatePrice']);

    // 4. الحوالات (Transfers)
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::patch('/transfers/{id}/update-status', [TransferController::class, 'update']);

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
});
