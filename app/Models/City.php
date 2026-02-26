<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{  protected $fillable = ['name', 'country_id'];
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function users()
    { 
        return $this->hasMany(User::class);
    }
}
