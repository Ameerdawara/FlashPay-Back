<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class OfficeController extends Controller
{
    // 1. عرض كل المكاتب (Index)
    public function index()
    {
        $offices = Office::with(['city','mainSafe'])->get(); // جلب المكاتب مع معلومات مدنها
        return response()->json([
            'status' => 'success',
            'data' => $offices
        ], 200);
    }


   public function store(Request $request)
{
    $validated = $request->validate([
        'city_id' => 'required|exists:cities,id',
        'name'    => 'required|string|max:255|unique:offices,name',
        'address' => 'nullable|string',
        'status'  => 'boolean',
        'balance' => 'required|numeric|min:0' // رصيد الصندوق الرئيسي الافتتاحي
    ]);

    try {
        // نبدأ عملية الـ Transaction
        $office = DB::transaction(function () use ($validated) {

            // 1. إنشاء المكتب
            $office = Office::create([
                'city_id' => $validated['city_id'],
                'name'    => $validated['name'],
                'address' => $validated['address'] ?? null,
                'status'  => $validated['status'] ?? true,
            ]);

            // 2. إنشاء الصندوق الرئيسي (Main Safe)
            $office->mainSafe()->create([
                'balance' => $validated['balance']
            ]);

            // 3. إنشاء صندوق التداول (Trading Safe) للدولار (currency_id = 1)
            $office->tradingSafes()->create([
                'currency_id' => 1,
                'balance'     => 0, // الرصيد الافتتاحي صفر
                'cost'        => 0  // التكلفة الافتتاحية صفر
            ]);

            return $office;
        });

        // جلب المكتب مع صناديقه لإعادتها في الرد
        return response()->json([
            'status' => 'success',
            'data' => $office->load(['mainSafe', 'tradingSafes']) 
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create office and safes: ' . $e->getMessage()
        ], 500);
    }
}
    // 5. حذف مكتب (Destroy)
    public function destroy($id)
    {
        $office = Office::find($id);
        if (!$office) {
            return response()->json(['message' => 'Office not found'], 404);
        }

        // ملاحظة: في أنظمة الحوالات يفضل الـ Soft Delete، لكن هنا سنحذف نهائياً
        $office->delete();
        return response()->json(['status' => 'success', 'message' => 'Office deleted'], 200);
    }
}
