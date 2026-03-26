<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Transfer;

// تصريح القناة الخاصة بالحوالة
// يتحقق أن المستخدم طرف في هذه الحوالة
Broadcast::channel('transfer.{transferId}', function ($user, $transferId) {
    $transfer = Transfer::find($transferId);
    if (!$transfer) return false;

    return $user->id === $transfer->sender_id
        || $user->id === $transfer->destination_agent_id;
});
