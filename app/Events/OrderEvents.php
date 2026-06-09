<?php

namespace App\Events;

use App\Models\Order;
use App\Models\VendorWithdrawRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCompleted { use Dispatchable, SerializesModels; public function __construct(public Order $order) {} }
class OrderShipped { use Dispatchable, SerializesModels; public function __construct(public Order $order) {} }
class OrderCancelled { use Dispatchable, SerializesModels; public function __construct(public Order $order) {} }
class WithdrawApproved { use Dispatchable, SerializesModels; public function __construct(public VendorWithdrawRequest $withdraw) {} }
class NewVendorRegistered { use Dispatchable, SerializesModels; public function __construct(public $shop) {} }
