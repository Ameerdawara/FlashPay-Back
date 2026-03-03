<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            CountrySeeder::class,
            CitySeeder::class,
            CurrencySeeder::class,
            SuperAdminSeeder::class,
        ]);
        \App\Models\User::create([
            'name' => 'Ameer Admin',
            'email' => 'admin111@flashpay.com',
            'phone' => '093123456711', // تأكد من إضافة رقم هاتف
            'password' => bcrypt('password'),
            'role' => 'admin',
            'country_id' => 1, // ربطه بأول دولة من الـ Seeder
        ]);
        
    }
}
