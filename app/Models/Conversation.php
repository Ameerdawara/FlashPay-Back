<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['transfer_id', 'customer_id', 'agent_id'];
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
