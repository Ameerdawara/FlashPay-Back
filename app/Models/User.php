<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'office_id',
        'country_id',
        'city_id',
        'is_active',
        // ✅ الثلاث صور المنفصلة بدل id_card_image الواحدة
        'selfie_with_id',
        'id_card_front',
        'id_card_back',
        'fcm_token',
        'agent_profit_ratio',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'agent_profit_ratio' => 'float',
        ];
    }

    // ── العلاقات ──────────────────────────────────────────────────────────

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /** صندوق المندوب (Polymorphic) */
    public function mainSafe()
    {
        return $this->morphOne(MainSafe::class, 'owner');
    }

    /** الحوالات التي أرسلها المستخدم */
    public function sentTransfers()
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }
}
