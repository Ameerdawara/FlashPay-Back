<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class OfficeSafe extends Model
{
    protected $fillable = ['office_id', 'balance'];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
