<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalTransfer extends Model
{
    protected $fillable = [
        'office_id', 
        'sender_name', 
        'receiver_name', 
        'amount', 
        'commission', 
        'is_paid', 
        'transfer_date'
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'transfer_date' => 'date',
    ];

    // العلاقة مع المكتب
    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}