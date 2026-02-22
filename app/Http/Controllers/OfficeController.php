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
        $offices = Office::with('city')->get(); // جلب المكاتب مع معلومات مدنها
        return response()->json([
            'status' => 'success',
            'data' => $offices
        ], 200);
    }

    // 2. إنشاء مكتب جديد (Store)

    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,id',
            'name'    => 'required|string|max:255|unique:offices,name',
            'address' => 'nullable|string',
            'status'  => 'boolean',
            // أضفنا التحقق من الرصيد الافتتاحي هنا
            'balance' => 'required|numeric|min:0'
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

                // 2. إنشاء الصندوق المرتبط بالمكتب
                // لارافيل سيعوض owner_id و owner_type تلقائياً
                $office->mainSafe()->create([
                    'balance' => $validated['balance']
                ]);

                return $office;
            });

            // جلب المكتب مع صندوقه الجديد لإعادته في الرد
            return response()->json([
                'status' => 'success',
                'data' => $office->load('mainSafe')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create office and safe: ' . $e->getMessage()
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
