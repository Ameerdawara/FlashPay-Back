<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'city_id',
        'name',
        'address',
        'status',
    ];
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function users()
    { // الموظفين التابعين للمكتب
        return $this->hasMany(User::class);
    }
    public function mainSafe()
    {
        return $this->morphOne(MainSafe::class, 'owner');
    }
    public function tradingSafe()
    {

        return $this->hasOne(TradingSafe::class, 'office_id');
    }
}
