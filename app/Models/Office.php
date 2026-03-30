<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'office_id',
        'currency_id',
        'cost',
        'city_id',
        'name',
        'address',
        'status'
    ];
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
    public function safe()
    {
        return $this->hasOne(OfficeSafe::class);
    }
    public function tradingTransactions()
    {
        return $this->hasMany(TradingTransaction::class);
    }
    public function internalTransfers()
    {
        return $this->hasMany(InternalTransfer::class);
    }
    
}
