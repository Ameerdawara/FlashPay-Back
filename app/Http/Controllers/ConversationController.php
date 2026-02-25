<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    //نبلش محادثة 
    public function startConversation(Request $request) {
    $validated = $request->validate([
        'transfer_id' => 'required|exists:transfers,id',
    ]);

    // منشان إذا كانت  محادثة مفتوحة أصلاً لهي الحوالة
    $conversation = Conversation::firstOrCreate([
        'transfer_id' => $validated['transfer_id'],
        'customer_id' => Auth::id(), 
    ]);

    return response()->json($conversation->load('messages'));
}
// 2. عرض قائمة المحادثات (للموظف ليختار منها)

public function index() {
    $user = Auth::user();
    
    // إذا كان موظف، يرى المحادثات التابعة لمكتبه أو غير المستلمة
    if (in_array($user->role, ['admin', 'super_admin'])) {
        return Conversation::with(['customer', 'transfer'])
            ->latest('updated_at')
            ->get();
    }

    return Conversation::where('customer_id', $user->id)
        ->with('messages')
        ->get();
}
}
