<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    // type: 'usd' (افتراضي) أو 'syp'
    protected $fillable = ['currency_id', 'type', 'min_amount', 'max_amount', 'rate'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}