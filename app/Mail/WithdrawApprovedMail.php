<?php

namespace App\Mail;

use App\Models\VendorWithdrawRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VendorWithdrawRequest $withdraw) {}

    public function build()
    {
        $locale = $this->recipientLocale($this->withdraw->vendor);
        $subject = $locale === 'en' ? 'Withdrawal Approved' : 'Penarikan Dana Disetujui';

        return $this->locale($locale)->subject($subject)->view('mail.withdraw-approved');
    }

    /** Preferensi penerima via users.locale (fallback id); aman bila kolom belum ada. */
    private function recipientLocale(mixed $user): string
    {
        try {
            $candidate = $user?->getAttribute('locale') ?? $user?->getAttribute('preferred_locale') ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                $base = strtolower(explode('-', str_replace('_', '-', trim($candidate)))[0]);

                return $base === 'en' ? 'en' : 'id';
            }
        } catch (\Throwable) {
            // Abaikan.
        }

        return 'id';
    }
}
