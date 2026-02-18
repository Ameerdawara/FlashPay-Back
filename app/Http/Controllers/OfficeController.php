<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;

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
            'status'  => 'boolean'
        ]);

        $office = Office::create($validated);
        return response()->json(['status' => 'success', 'data' => $office], 201);
    }

    // 3. عرض مكتب واحد بالتفصيل (Show)
    public function show($id)
    {
        $office = Office::with('city')->find($id);
        if (!$office) {
            return response()->json(['message' => 'Office not found'], 404);
        }
        return response()->json(['status' => 'success', 'data' => $office], 200);
    }

    // 4. تحديث بيانات مكتب (Update)
    public function update(Request $request, $id)
    {
        $office = Office::find($id);
        if (!$office) {
            return response()->json(['message' => 'Office not found'], 404);
        }

        $validated = $request->validate([
            'city_id' => 'sometimes|exists:cities,id',
            'name'    => 'sometimes|string|max:255|unique:offices,name,' . $id,
            'address' => 'nullable|string',
            'status'  => 'boolean'
        ]);

        $office->update($validated);
        return response()->json(['status' => 'success', 'data' => $office], 200);
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