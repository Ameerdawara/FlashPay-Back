<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // العملة المرجعية والعالمية
            ['name' => 'الدولار الأمريكي', 'code' => 'USD', 'price' => '1.0000', 'main_price' => '0.9900'],
            ['name' => 'USDT', 'code' => 'USDT', 'price' => '1.0000', 'main_price' => '0.9950'],
            ['name' => 'اليورو', 'code' => 'EUR', 'price' => '1.1850', 'main_price' => '1.1700'],
            ['name' => 'الجنيه الإسترليني', 'code' => 'GBP', 'price' => '1.3630', 'main_price' => '1.3500'],
            
            // عملات عربية
            ['name' => 'الليرة السورية', 'code' => 'SYP', 'price' => '0.000067', 'main_price' => '0.000065'],
            ['name' => 'الدرهم الإماراتي', 'code' => 'AED', 'price' => '0.2723', 'main_price' => '0.2700'],
            ['name' => 'الريال السعودي', 'code' => 'SAR', 'price' => '0.2667', 'main_price' => '0.2650'],
            ['name' => 'الدينار الكويتي', 'code' => 'KWD', 'price' => '3.2615', 'main_price' => '3.2500'],
            ['name' => 'الريال القطري', 'code' => 'QAR', 'price' => '0.2747', 'main_price' => '0.2720'],
            ['name' => 'الليرة التركية', 'code' => 'TRY', 'price' => '0.0228', 'main_price' => '0.0220'],
            ['name' => 'الجنيه المصري', 'code' => 'EGP', 'price' => '0.0214', 'main_price' => '0.0205'],
            
            // عملات أخرى
            ['name' => 'الين الياباني', 'code' => 'JPY', 'price' => '0.0065', 'main_price' => '0.0063'],
            ['name' => 'الدولار الكندي', 'code' => 'CAD', 'price' => '0.7334', 'main_price' => '0.7250'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']], // البحث بالكود لمنع التكرار
                $currency
            );
        }
    }
}