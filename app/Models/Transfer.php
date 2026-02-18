<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
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
