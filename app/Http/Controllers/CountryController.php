<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;


class CountryController extends Controller
{
    public function index()
    {
        // جلب جميع الدول
        $countries = Country::all();

        return response()->json([
            'status' => 'success',
            'count'  => $countries->count(),
            'data'   => $countries
        ], 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'code' => 'required|string|max:10|unique:countries,code',
        ], [

            'name.unique' => 'هذه الدولة مضافة مسبقاً.',
            'code.unique' => 'هذا الرمز مستخدم بالفعل.',
        ]);

        $country = Country::create($validatedData);
        return response()->json([
            'status' => 'success',
            'message' => 'Country created successfully',
            'data' => $country
        ], 201);
    }
}
