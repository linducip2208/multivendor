<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Events\OrderShipped;
use App\Events\OrderCancelled;
use App\Events\WithdrawApproved;
use App\Events\NewVendorRegistered;

class OrderEventListener
{
    public function handleCompleted(OrderCompleted $event): void
    {
        app(\App\Services\OrderService::class)->complete($event->order);
        \Illuminate\Support\Facades\Mail::to($event->order->customer->email)->queue(new \App\Mail\OrderConfirmation($event->order));
    }

    public function handleShipped(OrderShipped $event): void
    {
        \Illuminate\Support\Facades\Mail::to($event->order->customer->email)->queue(new \App\Mail\OrderShippedMail($event->order));
    }

    public function handleCancelled(OrderCancelled $event): void
    {
        app(\App\Services\OrderService::class)->cancel($event->order);
    }

    public function handleWithdrawApproved(WithdrawApproved $event): void
    {
        $vendor = $event->withdraw->vendor;
        \Illuminate\Support\Facades\Mail::to($vendor->email)->queue(new \App\Mail\WithdrawApprovedMail($event->withdraw));
    }

    public function handleNewVendor(NewVendorRegistered $event): void {}
}
