<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/debug-db', function() {
    $tables = DB::select("SELECT * FROM users ");
    return response()->json($tables);
});
