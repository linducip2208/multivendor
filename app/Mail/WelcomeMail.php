<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public mixed $user) {}

    public function build()
    {
        $locale = $this->recipientLocale($this->user);
        $subject = $locale === 'en'
            ? 'Welcome to '.config('app.name')
            : 'Selamat Datang di '.config('app.name');

        return $this->locale($locale)->subject($subject)->view('mail.welcome');
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
