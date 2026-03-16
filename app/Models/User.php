<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{   use HasApiTokens, HasFactory, Notifiable;
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
        'id_card_image'
    ];
    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function country()
    { // للمناديب فقط
        return $this->belongsTo(Country::class);
    }
    public function city()
    { // للمناديب فقط
        return $this->belongsTo(City::class);
    }

    // إذا كان المندوب له صندوق (Polymorphic)
    public function mainSafe()
    {
        return $this->morphOne(MainSafe::class, 'owner');
    }

    // الحوالات التي أرسلها الزبون
    public function sentTransfers()
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
