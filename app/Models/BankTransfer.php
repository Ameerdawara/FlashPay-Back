<?php
// =============================================================================
//  BankTransfer.php — Model
//  المسار: app/Models/BankTransfer.php
// =============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    use HasFactory;
protected $table = 'bank_transfer';
    protected $fillable = [
        'agent_id',        // الوكيل الذي أرسل الطلب
        'bank_name',       // اسم البنك
        'account_number',  // رقم الحساب
        'full_name',       // اسم صاحب الحساب (صاحب الحساب البنكي)
        'recipient_name',  // اسم المستلم الفعلي للمبلغ
        'phone',
        'destination_country', // الدولة الوجهة
        'destination_city',       // رقم الموبايل
        'amount',          // المبلغ (USD)
        'currency_id',     // العملة المختارة
        'notes',           // ملاحظات اختيارية
        'status',          // pending | admin_approved | rejected | completed
        'approved_by',     // super_admin / admin ID الذي وافق
        'cashier_id',      // الكاشير الذي أكمل العملية وطبع الإيصال
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    // ── العلاقات ──

    /** الوكيل الذي أرسل الطلب */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** المشرف الذي وافق/رفض */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** الكاشير الذي أتم التسليم وطبع الإيصال */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** العملة المختارة */
    public function currency()
    {
        return $this->belongsTo(\App\Models\Currency::class, 'currency_id');
    }
}
