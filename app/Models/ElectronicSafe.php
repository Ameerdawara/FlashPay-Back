<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectronicSafe extends Model
{
    use HasFactory;

    protected $fillable = [
        'office_id',
        'syp_sham_cash',
        'usd_sham_cash',
        'usdt',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}