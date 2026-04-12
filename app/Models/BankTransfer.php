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

    protected $fillable = [
        'agent_id',       // الوكيل الذي أرسل الطلب
        'bank_name',      // اسم البنك
        'account_number', // رقم الحساب
        'full_name',      // اسم صاحب الحساب
        'phone',          // رقم الموبايل
        'amount',         // المبلغ (USD)
        'notes',          // ملاحظات اختيارية
        'status',         // pending | approved | rejected
        'approved_by',    // super_admin ID الذي وافق
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
}
