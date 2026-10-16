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

    protected $casts = [
        'is_read' => 'boolean',
    ];

    /**
     * رابط الصورة يمر عبر Laravel نفسه (route: /api/media?p=chat_images/xxx.jpg)
     * فلا يعتمد على nginx ولا على الرابط الرمزي public/storage.
     * المخزَّن في قاعدة البيانات يبقى مساراً نسبياً (chat_images/xxx.jpg).
     */
    public function getImageAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // رابط كامل مسبقاً
        if (preg_match('#^https?://#i', $value)) {
            return preg_replace('#^http://#i', 'https://', $value);
        }

        $path = ltrim($value, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return preg_replace('#^http://#i', 'https://', url('/api/media') . '?p=' . rawurlencode($path));
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