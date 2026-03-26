<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
// التحقق من صلاحية الدخول لغرفة محادثة الحوالة
Broadcast::channel('transfer.{transferId}', function ($user, $transferId) {
    // مؤقتاً للتجربة سنسمح بالدخول، لاحقاً يمكنك التأكد أن اليوزر هو المرسل أو الوكيل
    return true;
});
