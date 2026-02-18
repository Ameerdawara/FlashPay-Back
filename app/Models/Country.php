<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public function cities() {
    return $this->hasMany(City::class);
}

public function users() { // المناديب في هذه الدولة
    return $this->hasMany(User::class);
}
}
