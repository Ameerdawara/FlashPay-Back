<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProfitSafe extends Model
{
    protected $fillable = ['office_id', 'profit_trade', 'profit_main'];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
