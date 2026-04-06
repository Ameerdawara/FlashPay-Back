<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MainSafe extends Model
{
    protected $fillable = [
        'balance',
        'agent_profit_ratio', // نسبة ربح المندوب (0-100%)
        'agent_profit',       // إجمالي أرباح المندوب المتراكمة
        'owner_id',
        'owner_type',
    ];

    protected $casts = [
        'balance'            => 'float',
        'agent_profit_ratio' => 'float',
        'agent_profit'       => 'float',
    ];

    public function owner()
    {
        return $this->morphTo();
    }
}
