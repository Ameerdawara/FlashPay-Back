<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'sender_id',
        'receiver_id',
        'image',
        'message',
        'is_read',
    ];
    public function getImageAttribute($value)
    {
        if ($value) {
            return asset('storage/' . $value); 
        }
        return null;
    }

    // علاقة الرسالة بالحوالة
    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    // علاقة الرسالة بالمرسل
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // علاقة الرسالة بالمستقبل
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}