<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currencies:update';
    protected $description = 'جلب أحدث أسعار العملات من API خارجي وتحديث قاعدة البيانات';

    public function handle()
    {
        $this->info('جاري جلب أسعار العملات...');
        try {
            $response = Http::get('https://api.exchangerate-api.com/v4/latest/USD');

            if ($response->successful()) {
                $rates = $response->json()['rates'];
                $currencies = Currency::all();

                foreach ($currencies as $currency) {
                    if (isset($rates[$currency->code])) {
                        // تحديث حقل السعر في قاعدة البيانات
                        $currency->update(['price' => $rates[$currency->code]]);
                        $this->info("تم تحديث {$currency->name} بنجاح.");
                    }
                }
                $this->info('تم تحديث جميع أسعار العملات بنجاح!');
            } else {
                $this->error('فشل الاتصال بـ API العملات.');
            }
        } catch (\Exception $e) {
            $this->error('حدث خطأ: ' . $e->getMessage());
        }
    }
}