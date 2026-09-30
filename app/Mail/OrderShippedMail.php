<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function build()
    {
        $locale = $this->recipientLocale($this->order->customer);
        $subject = $locale === 'en'
            ? 'Order Shipped #'.$this->order->order_number
            : 'Pesanan Dikirim #'.$this->order->order_number;

        return $this->locale($locale)->subject($subject)->view('mail.order-shipped');
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
