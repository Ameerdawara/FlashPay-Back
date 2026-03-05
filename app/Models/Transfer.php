<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_code',
        'sender_id',
        'amount',
        'currency_id',
        'fee',
        'destination_office_id',
        'destination_agent_id',
        'receiver_name',
        'receiver_phone',
        'receiver_id_image',
        'status',
        'amount_in_usd'
    ];
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function destinationOffice()
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }
}
