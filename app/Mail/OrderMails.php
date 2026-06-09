<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\VendorWithdrawRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable { use Queueable, SerializesModels; public function __construct(public Order $order) {} public function build() { return $this->subject('Pesanan Dikonfirmasi #'.$this->order->order_number)->view('mail.order-confirmation'); } }
class OrderShippedMail extends Mailable { use Queueable, SerializesModels; public function __construct(public Order $order) {} public function build() { return $this->subject('Pesanan Dikirim #'.$this->order->order_number)->view('mail.order-shipped'); } }
class WithdrawApprovedMail extends Mailable { use Queueable, SerializesModels; public function __construct(public VendorWithdrawRequest $withdraw) {} public function build() { return $this->subject('Withdraw Disetujui')->view('mail.withdraw-approved'); } }
class WelcomeMail extends Mailable { use Queueable, SerializesModels; public function __construct(public $user) {} public function build() { return $this->subject('Selamat Datang di '.config('app.name'))->view('mail.welcome'); } }
