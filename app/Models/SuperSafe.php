<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperSafe extends Model
{
    protected $fillable = ['balance'];

    // دائماً نستخدم السجل الأول (صندوق واحد فقط للسوبر أدمن)
    public static function instance(): self
    {
        return self::firstOrCreate([], ['balance' => 0]);
    }
}
