<?php

namespace App\Observers;

use App\Models\Transfer;
use App\Models\TransferHistory;
class TransferObserver
{
    /**
     * Handle the Transfer "created" event.
     */
    public function created(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "updated" event.
     */
    public function updated(Transfer $transfer)
    {
        // التحقق مما إذا كان هناك تغيير فعلي في البيانات
        if ($transfer->wasChanged()) {

            // جلب الحقول التي تغيرت فقط (القيم الجديدة)
            $changes = $transfer->getChanges();
            unset($changes['updated_at']); // لا نحتاج لتسجيل وقت التحديث كونه يتغير دائماً

            // إذا كان هناك تغييرات تستحق الحفظ
            if (count($changes) > 0) {
                // استخراج القيم القديمة للحقول التي تغيرت فقط
                $oldData = array_intersect_key($transfer->getOriginal(), $changes);

                TransferHistory::create([
                    'transfer_id' => $transfer->id,
                    'admin_id'    => request()->user()?->id, 
                    'old_data'    => $oldData,
                    'new_data'    => $changes,
                    'action'      => 'updated',
                ]);
            }
        }
    }

    /**
     * Handle the Transfer "deleted" event.
     */
    public function deleted(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "restored" event.
     */
    public function restored(Transfer $transfer): void
    {
        //
    }

    /**
     * Handle the Transfer "force deleted" event.
     */
    public function forceDeleted(Transfer $transfer): void
    {
        //
    }
}
