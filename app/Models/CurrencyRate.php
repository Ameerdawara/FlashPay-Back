<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $fillable = ['currency_id', 'min_amount', 'max_amount', 'rate'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}