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
        return $this->subject('Selamat Datang di '.config('app.name'))->view('mail.welcome');
    }
}
