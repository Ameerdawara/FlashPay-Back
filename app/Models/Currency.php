<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['name', 'code', 'price','main_price'];
    public function transfers()
    {
        return $this->hasMany(Transfer::class);
    }
    public function tradingSafes()
    {

        return $this->hasMany(TradingSafe::class);
    }
    public function rates()
{
    return $this->hasMany(CurrencyRate::class)->orderBy('min_amount');
}
}
