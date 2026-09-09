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
        return $this->subject('Withdraw Disetujui')->view('mail.withdraw-approved');
    }
}
