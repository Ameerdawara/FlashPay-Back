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
            ['name' => 'الدولار الأمريكي', 'code' => 'USD', 'price' => 1.0000],
            ['name' => 'USDT', 'code' => 'USDT', 'price' => 1.0000],
            ['name' => 'اليورو', 'code' => 'EUR', 'price' => 1.1850],
            ['name' => 'الجنيه الإسترليني', 'code' => 'GBP', 'price' => 1.3630],
            
            // عملات عربية (سعر الصرف مقابل 1 دولار تقريباً)
            ['name' => 'الليرة السورية', 'code' => 'SYP', 'price' => 0.000067],
            ['name' => 'الدرهم الإماراتي', 'code' => 'AED', 'price' => 0.2723],
            ['name' => 'الريال السعودي', 'code' => 'SAR', 'price' => 0.2667],
            ['name' => 'الدينار الكويتي', 'code' => 'KWD', 'price' => 3.2615],
            ['name' => 'الريال القطري', 'code' => 'QAR', 'price' => 0.2747],
            ['name' => 'الليرة التركية', 'code' => 'TRY', 'price' => 0.0228], // سعر تقريبي متوقع
            ['name' => 'الجنيه المصري', 'code' => 'EGP', 'price' => 0.0214], // سعر تقريبي متوقع
            
            // عملات أخرى
            ['name' => 'الين الياباني', 'code' => 'JPY', 'price' => 0.0065],
            ['name' => 'الدولار الكندي', 'code' => 'CAD', 'price' => 0.7334],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']], // البحث بالكود لمنع التكرار
                $currency
            );
        }
    }
}