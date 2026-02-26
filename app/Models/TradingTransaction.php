<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingTransaction extends Model
{
    // الحقول التي تسمح بالإدخال الجماعي
    protected $fillable = [
        'office_id',
        'currency_id',
        'user_id',
        'type',
        'amount',
        'price',
        'cost_at_time',
        'profit',
        'transaction_date'
    ];

    // علاقة مع المكتب
    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    // علاقة مع العملة
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    // علاقة مع المستخدم (الموظف) الذي قام بالعملية
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}