<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    // يُرسل مع كل حوالة في الـ JSON: المبلغ الذي يستلمه المستلم فعلياً
    protected $appends = ['receive_amount'];

    protected $fillable = [
        'tracking_code',
        'sender_id',
        'amount',
        'currency_id',
        'send_currency_id',
        'fee',
        'agent_profit',
        'destination_office_id',
        // 'destination_agent_id',
        'destination_country_id', // أضفناه هنا
        'destination_city',       // أضفناه هنا
        'receiver_name',
        'receiver_phone',
        'receiver_id_image',
        'status',
        'amount_in_usd',
        'rate_type',      // usd | syp
        'applied_rate',   // السعر المطبّق على الحوالة
        'amount_in_syp',  // المبلغ بالسوري (حوالات السوري فقط)
        'fee_syp',        // الربح بالسوري (حوالات السوري فقط)
    ];
    /**
     * المبلغ الذي يستلمه المستلم بعملة الاستلام (currency):
     * - حوالة بالسوري  → amount_in_syp (المحسوب بأسعار السوري وقت الإنشاء)
     * - حوالة بالدولار → amount_in_usd ÷ سعر عملة الاستلام (السلوك القديم، ويساوي amount_in_usd للدولار)
     */
    public function getReceiveAmountAttribute(): float
    {
        if (($this->rate_type ?? Currency::RATE_USD) === Currency::RATE_SYP && $this->amount_in_syp !== null) {
            return (float) $this->amount_in_syp;
        }

        $price = (float) ($this->currency?->price ?? 1);
        $usd   = (float) ($this->amount_in_usd ?? 0);

        return $price > 0 ? $usd / $price : 0.0;
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
public function destinationCountry() {
    return $this->belongsTo(Country::class, 'destination_country_id');
}
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function sendCurrency()
    {
        return $this->belongsTo(Currency::class, 'send_currency_id');
    }
    public function destinationOffice()
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }
    public function histories()
    {
        return $this->hasMany(TransferHistory::class);
    }
}