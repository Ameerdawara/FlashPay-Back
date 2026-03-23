<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferHistory extends Model
{
    protected $fillable = ['transfer_id', 'admin_id', 'old_data', 'new_data', 'action'];

    // لكي يتعامل لارافيل مع الحقول كأنها مصفوفات تلقائياً
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
