<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;
use App\Models\CurrencyRate;

/**
 * أسعار اليوم حسب بطاقتي الأسعار (الاثنين 07/09/2026)
 *
 *  price     = دولار لكل 1 وحدة (بطاقة "التحويل بالدولار")
 *  price_syp = ليرة سورية لكل 1 وحدة (بطاقة "التحويل بالسوري")
 *
 *  usd_upper / syp_upper = [بداية الشريحة العليا بمبلغ العملة الأجنبية, سعرها]
 *  الشريحة الدنيا تُنشأ تلقائياً: من 0 حتى بداية العليا، وسعرها = السعر الأساسي.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // العملة المرجعية والعالمية
            ['name' => 'الدولار الأمريكي', 'code' => 'USD', 'price' => '1.0000', 'main_price' => '0.9900'],
            ['name' => 'USDT', 'code' => 'USDT', 'price' => '1.0000', 'main_price' => '0.9950'],
            [
                'name' => 'اليورو', 'code' => 'EUR',
                'price' => '1.0200',                              // 1€ = 1.02$
                'usd_upper' => [1000, 1.07],                      // 1070$ = 1000€
                'price_syp' => '126.5',                           // 1€ = 126.5 ل.س
                'syp_upper' => [1000, 137],                       // 1000€ = 137,000 ل.س
            ],
            ['name' => 'الجنيه الإسترليني', 'code' => 'GBP', 'price' => '1.3630', 'main_price' => '1.3500'],

            // عملات عربية
            ['name' => 'الليرة السورية', 'code' => 'SYP', 'price' => '0.000067', 'main_price' => '0.000065'],
            [
                'name' => 'الدرهم الإماراتي', 'code' => 'AED',
                'price' => '0.2640',                              // 1 AED = 0.264$
                'usd_upper' => [3800, round(1000 / 3800, 6)],     // 3,800 AED = 1000$
                'price_syp' => '33',                              // 1 AED = 33 ل.س
                'syp_upper' => [304, round(10000 / 304, 6)],      // 304 AED = 10,000 ل.س
            ],
            [
                'name' => 'الريال السعودي', 'code' => 'SAR',
                'price' => '0.2560',                              // 1 SAR = 0.256$
                'usd_upper' => [3900, round(1000 / 3900, 6)],     // 3,900 SAR = 1000$
                'price_syp' => '34',                              // 1 SAR = 34 ل.س
                'syp_upper' => [312, round(10000 / 312, 6)],      // 312 SAR = 10,000 ل.س
            ],
            [
                'name' => 'الدينار الكويتي', 'code' => 'KWD',
                'price' => '3.1250',                              // 1 KD = 3.125$
                'usd_upper' => [318, round(1000 / 318, 6)],       // 318 KD = 1000$
                'price_syp' => '400',                             // 1 KD = 400 ل.س
                'syp_upper' => [26, round(10000 / 26, 6)],        // 26 KD = 10,000 ل.س
            ],
            ['name' => 'الريال القطري', 'code' => 'QAR', 'price' => '0.2747', 'main_price' => '0.2720'],
            ['name' => 'الليرة التركية', 'code' => 'TRY', 'price' => '0.0228', 'main_price' => '0.0220'],
            ['name' => 'الجنيه المصري', 'code' => 'EGP', 'price' => '0.0214', 'main_price' => '0.0205'],

            // عملات أخرى
            ['name' => 'الين الياباني', 'code' => 'JPY', 'price' => '0.0065', 'main_price' => '0.0063'],
            ['name' => 'الدولار الكندي', 'code' => 'CAD', 'price' => '0.7334', 'main_price' => '0.7250'],

            // عملات جديدة من البطاقة
            [
                'name' => 'الروبل الروسي', 'code' => 'RUB',
                'price' => '0.0100',                              // 100 Rub = 1$
                'usd_upper' => [90000, round(1000 / 90000, 6)],   // 90,000 Rub = 1000$
                'price_syp' => '0.9',                             // 1 Rub = 0.9 ل.س  ⚠️ راجع الرقم (يوحي بـ 90 ل.س/$ بدل 125)
                'syp_upper' => [7200, round(10000 / 7200, 6)],    // 7,200 Rub = 10,000 ل.س
            ],
            ['name' => 'الدينار العراقي', 'code' => 'IQD', 'price' => '0.000645'], // 1$ = 1550 IQD

            // ── مُعلَّقة: صيغتها في البطاقة غير واضحة، فعِّلها بعد التأكد ──
            // ['name' => 'الدينار الأردني', 'code' => 'JOD', 'price' => '1.379310'],  // إن كان المقصود 0.725 دينار لكل $
            // الليرة اللبنانية: البطاقة "1$ = 0.97$" (<1000$) و"0.975$" (>1000$) — غير مفهومة كعملة
        ];

        foreach ($currencies as $data) {
            // أسعار السوري والشرائح تُعامَل بشكل منفصل عن بقية الحقول
            $sypPrice = $data['price_syp'] ?? null;
            $usdUpper = $data['usd_upper'] ?? null;
            $sypUpper = $data['syp_upper'] ?? null;
            unset($data['price_syp'], $data['usd_upper'], $data['syp_upper']);

            // البحث بالكود لمنع التكرار
            $currency = Currency::where('code', $data['code'])->first();

            if ($currency) {
                // عملة موجودة: نحدّث الحقول المذكورة فقط (main_price لا يُمسّ إن لم يُذكر)
                $currency->update($data);
            } else {
                // عملة جديدة: main_price إلزامي في الجدول (بلا قيمة افتراضية).
                // إن لم يُذكر يُضبط = السعر (ربح صفري حتى يحدده السوبر أدمن)
                $data['main_price'] = $data['main_price'] ?? $data['price'];
                $currency = Currency::create($data);
            }

            // سعر اليوم بالسوري (يُكتب فوق القديم)
            if ($sypPrice !== null) {
                $currency->update(['price_syp' => $sypPrice]);
            }

            // شرائح اليوم: نمسح القديمة (دولار + سوري) ثم ننشئ الجديدة
            if ($usdUpper !== null || $sypUpper !== null) {
                CurrencyRate::where('currency_id', $currency->id)->delete();

                if ($usdUpper !== null) {
                    $this->createTiers($currency, Currency::RATE_USD, (float) $currency->price, $usdUpper);
                }
                if ($sypUpper !== null && $sypPrice !== null) {
                    $this->createTiers($currency, Currency::RATE_SYP, (float) $sypPrice, $sypUpper);
                }
            }
        }
    }

    /**
     * شريحتان: [0 → قبل الحد الأعلى] بالسعر الأساسي، و[الحد الأعلى → ∞] بسعر الشريحة العليا.
     */
    private function createTiers(Currency $currency, string $type, float $baseRate, array $upper): void
    {
        [$upperMin, $upperRate] = $upper;

        CurrencyRate::create([
            'currency_id' => $currency->id,
            'type'        => $type,
            'min_amount'  => 0,
            'max_amount'  => round($upperMin - 0.01, 2),
            'rate'        => $baseRate,
        ]);

        CurrencyRate::create([
            'currency_id' => $currency->id,
            'type'        => $type,
            'min_amount'  => $upperMin,
            'max_amount'  => null,
            'rate'        => $upperRate,
        ]);
    }
}