<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country; // تأكد من استدعاء الموديل الصحيح

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // دول الجوار (تركيز عالي جداً)
            ['name' => 'سوريا', 'code' => 'SY'],
            ['name' => 'تركيا', 'code' => 'TR'],
            ['name' => 'لبنان', 'code' => 'LB'],
            ['name' => 'الأردن', 'code' => 'JO'],
            ['name' => 'العراق', 'code' => 'IQ'],
            ['name' => 'مصر', 'code' => 'EG'],

            // دول الخليج العربي
            ['name' => 'المملكة العربية السعودية', 'code' => 'SA'],
            ['name' => 'الإمارات العربية المتحدة', 'code' => 'AE'],
            ['name' => 'الكويت', 'code' => 'KW'],
            ['name' => 'قطر', 'code' => 'QA'],
            ['name' => 'سلطنة عمان', 'code' => 'OM'],

            // أوروبا
            ['name' => 'ألمانيا', 'code' => 'DE'],
            ['name' => 'السويد', 'code' => 'SE'],
            ['name' => 'هولندا', 'code' => 'NL'],
            ['name' => 'النمسا', 'code' => 'AT'],
            ['name' => 'فرنسا', 'code' => 'FR'],
            ['name' => 'اليونان', 'code' => 'GR'],

            // الأمريكتين ودول أخرى
            ['name' => 'الولايات المتحدة الأمريكية', 'code' => 'US'],
            ['name' => 'كندا', 'code' => 'CA'],
            ['name' => 'البرازيل', 'code' => 'BR'],
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}