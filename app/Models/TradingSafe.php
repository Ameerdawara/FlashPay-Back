<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradingSafe extends Model
{
    protected $fillable = ['currency_id', 'balance', 'cost'];

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
