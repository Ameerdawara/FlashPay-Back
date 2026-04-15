<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/debug-db', function() {
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    return response()->json($tables);
});
