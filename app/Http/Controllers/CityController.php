<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {

        $query = City::query();


        if ($request->has('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        $cities = $query->with('country:id,name')->get();

        return response()->json([
            'status' => 'success',
            'count'  => $cities->count(),
            'data'   => $cities
        ], 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'       => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        // التحقق من عدم التكرار لنفس الدولة
        $exists = City::where('name', $request->name)
            ->where('country_id', $request->country_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'This city already exists in the selected country'
            ], 422); // 422 تعني خطأ في البيانات المدخلة
        }

        $city = City::create($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'City created successfully',
            'data' => $city
        ], 201);
    }
}
