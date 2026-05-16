<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperSafeLog extends Model
{
    protected $fillable = [
        'type', 'amount', 'office_id', 'office_name',
        'note', 'balance_before', 'balance_after'
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
