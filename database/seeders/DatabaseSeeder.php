<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Transfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. تشغيل السييدرز الأساسية
        $this->call([
            CountrySeeder::class,
            CitySeeder::class,
            CurrencySeeder::class,
            SuperAdminSeeder::class, // تأكد من تفعيلها إذا أردت
        ]);

        // 2. إنشاء مستخدم أدمن
        $admin = User::create([
            'name' => 'Ameer Admin',
            'email' => 'admin111@flashpay.com',
            'phone' => '093123456711',
            'password' => bcrypt('password'),
            'is_active' => true,
            'role' => 'admin',
            'country_id' => 1,
            'id_card_image'=>null
        ]);

        // 3. إنشاء حوالة تجريبية (Seed)
        Transfer::create([
            'tracking_code' => 'TRX-' . strtoupper(Str::random(8)), // توليد كود عشوائي
            'sender_id' => $admin->id, // استخدمنا الآيدي الخاص بالأدمن الذي أنشأناه للتو
            'amount' => 500.00,
            'send_currency_id' => 1,
            'currency_id' => 1, // افترضنا أن 1 هو الدولار، تأكد من وجوده بسبب الـ CurrencySeeder
            'fee' => 5.00,
            'destination_office_id' => null, // يمكنك وضع ID مكتب إذا كان لديك مكاتب في الداتا بيز
            'destination_agent_id' => null,
            'receiver_name' => 'أحمد محمد',
            'receiver_phone' => '0987654321',
            'receiver_id_image' => null,
            'status' => 'pending' // الحالة الافتراضية
        ]);
    }
}
