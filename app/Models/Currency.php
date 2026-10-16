<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Currency extends Model
{
    public const RATE_USD = 'usd';
    public const RATE_SYP = 'syp';

    protected $fillable = [
        'name',
        'code',
        'price',          // سعر البيع بالدولار
        'main_price',     // سعر التكلفة بالدولار
        'price_syp',      // سعر البيع بالسوري
        'main_price_syp', // سعر التكلفة بالسوري
    ];

    public function transfers()
    {
        return $this->hasMany(Transfer::class);
    }

    public function tradingSafes()
    {
        return $this->hasMany(TradingSafe::class);
    }

    /**
     * شرائح الدولار (السلوك القديم بدون أي تغيير).
     */
    public function rates()
    {
        return $this->hasMany(CurrencyRate::class)
            ->where('type', self::RATE_USD)
            ->orderBy('min_amount');
    }

    /**
     * شرائح السوري.
     */
    public function sypRates()
    {
        return $this->hasMany(CurrencyRate::class)
            ->where('type', self::RATE_SYP)
            ->orderBy('min_amount');
    }

    /**
     * الشرائح حسب النوع (تستخدم العلاقة المحمّلة مسبقاً إن وُجدت).
     */
    public function ratesFor(string $type): Collection
    {
        return $type === self::RATE_SYP ? $this->sypRates : $this->rates;
    }

    /**
     * هل هذه العملة هي الليرة السورية؟
     */
    public function isSyp(): bool
    {
        return strtoupper((string) $this->code) === 'SYP';
    }

    /**
     * جهة التصريف عندما تكون هذه العملة هي عملة الاستلام:
     * SYP → أسعار السوري، وأي عملة أخرى → أسعار الدولار.
     */
    public function receiveRateType(): string
    {
        return $this->isSyp() ? self::RATE_SYP : self::RATE_USD;
    }

    /**
     * السعر الافتراضي (عند عدم وجود شريحة مطابقة).
     */
    public function basePrice(string $type = self::RATE_USD): float
    {
        return $type === self::RATE_SYP
            ? (float) ($this->price_syp ?? 0)
            : (float) ($this->price ?? 1);
    }

    /**
     * سعر التكلفة (مع قيمة بديلة إذا لم يكن محدداً).
     */
    public function costPrice(string $type = self::RATE_USD, ?float $fallback = null): float
    {
        $cost = $type === self::RATE_SYP ? $this->main_price_syp : $this->main_price;

        return (float) ($cost ?? $fallback ?? $this->basePrice($type));
    }

    /**
     * سعر الصرف الفعلي لمبلغ معيّن: الشريحة المطابقة أو السعر الافتراضي.
     */
    public function effectiveRate(float $amount, string $type = self::RATE_USD): float
    {
        // إرسال بالليرة السورية واستلام بالليرة السورية: لا يوجد تصريف
        if ($type === self::RATE_SYP && $this->isSyp()) {
            return 1.0;
        }

        $tier = $this->ratesFor($type)
            ->sortBy('min_amount')
            ->first(function ($tier) use ($amount) {
                $min = (float) $tier->min_amount;
                $max = $tier->max_amount !== null ? (float) $tier->max_amount : INF;

                return $amount >= $min && $amount <= $max;
            });

        return $tier ? (float) $tier->rate : $this->basePrice($type);
    }

    /**
     * هل هذه العملة لها سعر مضبوط لهذا النوع؟
     * الدولار متاح دائماً، والسوري يحتاج سعراً افتراضياً أو شرائح.
     */
    public function supportsRateType(string $type): bool
    {
        if ($type !== self::RATE_SYP || $this->isSyp()) {
            return true;
        }

        return $this->basePrice(self::RATE_SYP) > 0 || $this->sypRates->isNotEmpty();
    }
}