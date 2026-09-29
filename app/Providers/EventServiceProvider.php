<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\DomainEventRelay;
use App\Listeners\OrderEventListener;
use App\Models\CouponUsage;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentGroup;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use App\Services\Webhooks\PayloadRedactor;
use App\Services\Webhooks\WebhookEventCatalogue;
use App\Services\Webhooks\WebhookService;
use App\Services\Webhooks\WebhookSigner;
use App\Services\Webhooks\WebhookStats;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayloadRedactor::class);
        $this->app->singleton(WebhookSigner::class);
        $this->app->singleton(WebhookEventCatalogue::class);
        $this->app->singleton(WebhookStats::class);
        $this->app->singleton(WebhookService::class);
    }

    public function boot(): void
    {
        $this->registerOrderListeners();
        $this->registerDomainRelay();
    }

    private function registerOrderListeners(): void
    {
        $listener = OrderEventListener::class;

        $events = [
            \App\Events\OrderShipped::class,
            \App\Events\OrderDelivered::class,
            \App\Events\OrderCompleted::class,
            \App\Events\OrderCancelled::class,
            \App\Events\WithdrawalApproved::class,
            \App\Events\WithdrawApproved::class,
            \App\Events\NewVendorRegistered::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, $listener);
        }
    }

    private function registerDomainRelay(): void
    {
        $relay = DomainEventRelay::class;

        $map = [
            Order::class => [
                'created' => 'onOrderCreated',
            ],
            OrderStatusHistory::class => [
                'created' => 'onOrderStatusCreated',
            ],
            PaymentGroup::class => [
                'created' => 'onPaymentCreated',
                'updated' => 'onPaymentUpdated',
            ],
            Refund::class => [
                'created' => 'onRefundCreated',
                'updated' => 'onRefundUpdated',
            ],
            Product::class => [
                'created' => 'onProductCreated',
                'updated' => 'onProductUpdated',
            ],
            ProductReview::class => [
                'created' => 'onReviewCreated',
            ],
            User::class => [
                'created' => 'onCustomerCreated',
            ],
            CouponUsage::class => [
                'created' => 'onCouponUsageCreated',
            ],
            VendorWithdrawRequest::class => [
                'created' => 'onWithdrawalCreated',
                'updated' => 'onWithdrawalUpdated',
            ],
            Shop::class => [
                'created' => 'onShopCreated',
                'updated' => 'onShopUpdated',
            ],
            Message::class => [
                'created' => 'onMessageCreated',
            ],
            SupportTicket::class => [
                'created' => 'onSupportTicketCreated',
            ],
        ];

        foreach ($map as $model => $hooks) {
            foreach ($hooks as $hook => $method) {
                Event::listen($model.'.'.$hook, [$relay, $method]);
            }
        }
    }
}
