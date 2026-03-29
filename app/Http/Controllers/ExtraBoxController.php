<?php

namespace App\Http\Controllers;

use App\Models\ExtraBox;
use Illuminate\Http\Request;

class ExtraBoxController extends Controller
{
    // عرض جميع الصناديق الإضافية
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => ExtraBox::all()
        ], 200);
    }

    // إنشاء صندوق جديد
    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'amount' => 'required|numeric',
            'office_id'=> 'required|exists:offices,id'

        ]);

        $box = ExtraBox::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء الصندوق بنجاح',
            'data' => $box
        ], 201);
    }

    // عرض صندوق محدد
    public function show($id)
    {
        $box = ExtraBox::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $box
        ], 200);
    }

    // تعديل صندوق موجود (تغيير الاسم أو الكمية)
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric'
        ]);

        $box = ExtraBox::findOrFail($id);
        $box->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'تم التعديل بنجاح',
            'data' => $box
        ], 200);
    }

    // حذف صندوق
    public function destroy($id)
    {
        $box = ExtraBox::findOrFail($id);
        $box->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الصندوق بنجاح'
        ], 200);
    }
}
