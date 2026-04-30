<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectronicSafeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'office_id',
        'currency_type',
        'action_type',
        'amount',
        'commission_rate',
        'net_amount',
        'profit',
        'note',
        'performed_by',   // ← المنفذ (user_id)
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    /** المستخدم المنفذ للعملية */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
