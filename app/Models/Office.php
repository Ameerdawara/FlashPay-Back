<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = ['city_id','address','office_id','name' ,'currency_id', 'balance', 'cost'];
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function users()
    { // الموظفين التابعين للمكتب
        return $this->hasMany(User::class);
    } // داخل كلاس Office
    public function tradingSafes()
    {
        return $this->hasMany(TradingSafe::class, 'office_id');
    }

    // وتأكد أيضاً من وجود علاقة الصندوق الرئيسي إذا كنت تستخدمها
    public function mainSafe()
    {
        return $this->morphOne(MainSafe::class, 'owner');
    }
    public function tradingTransactions()
    {
        return $this->hasMany(TradingTransaction::class);
    }
    
}
