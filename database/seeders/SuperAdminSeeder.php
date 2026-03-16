<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name'     => 'Anas Admin',
            'email'    => 'admin@flashpay.com',
            'phone'    => '+351920653719',
            'password' => Hash::make('A@a12345678'), // تأكد من تغييرها لاحقاً
            'role'     => 'super_admin',
            // المكاتب والمدن تترك null حالياً لأن الميجريشن يسمح بذلك
            'office_id'  => null,
            'country_id' => null,
            'city_id'    => null,
            'is_active'=>true
        ]);
    }
}
