<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewVendorRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public mixed $shop) {}
}
