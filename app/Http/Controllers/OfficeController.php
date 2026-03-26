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
    // أضفنا 'safe' هنا لتظهر في قائمة المكاتب
    $offices = Office::with(['city', 'mainSafe', 'safe'])->get(); 
    return response()->json(['status' => 'success', 'data' => $offices]);
}
// 4. تعديل مكتب (Update)
public function update(Request $request, $id)
{
    // التأكد أن المستخدم هو super_admin
    if ($request->user()->role !== 'super_admin') {
        return response()->json(['message' => 'غير مصرح لك بالقيام بهذه العملية'], 403);
    }

    $office = Office::find($id);
    if (!$office) {
        return response()->json(['message' => 'المكتب غير موجود'], 404);
    }

    $validated = $request->validate([
        'city_id' => 'sometimes|exists:cities,id',
        'name'    => 'sometimes|string|max:255|unique:offices,name,' . $id,
        'address' => 'nullable|string',
    ]);

    $office->update($validated);

    return response()->json(['status' => 'success', 'data' => $office]);
}
public function destroy(Request $request, $id)
{
    // 1. التحقق من الصلاحية (فقط super_admin)
    if ($request->user()->role !== 'super_admin') {
        return response()->json(['message' => 'غير مصرح لك بحذف المكاتب'], 403);
    }

    $office = Office::find($id);
    if (!$office) {
        return response()->json(['message' => 'المكتب غير موجود'], 404);
    }

    try {
        DB::transaction(function () use ($office) {
            $office->safe()->delete(); // !!! إضافة حذف خزنة المكتب أولاً
            $office->mainSafe()->delete();
            $office->tradingSafes()->delete();
            \App\Models\User::where('office_id', $office->id)->update(['office_id' => null]);
            $office->delete();
        });
        return response()->json(['status' => 'success', 'message' => 'تم الحذف بنجاح']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => 'فشل الحذف: ' . $e->getMessage()], 500);
    }
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
                'balance' => 0
            ]);
  $office->safe()->create(['balance'=>$validated['balance']]);
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
            'data' => $office->load(['mainSafe', 'tradingSafes','safe'])
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create office and safes: ' . $e->getMessage()
        ], 500);
    }
}


}
