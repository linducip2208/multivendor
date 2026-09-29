<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\TrackOrderController;
use App\Support\Currency;

/*
|--------------------------------------------------------------------------
| Vendor navigation
|--------------------------------------------------------------------------
| Mirrors config/navigation.php. Seller-facing groups match the operating
| system a real merchant expects: store, orders, customers, marketing,
| finance, analytics, AI, support and settings.
|
*/

return [

    [
        'label' => 'Dashboard',
        'icon' => 'home',
        'items' => [
            ['route' => 'vendor.dashboard', 'label' => 'Ringkasan', 'icon' => 'dashboard', 'match' => 'vendor.dashboard'],
        ],
    ],

    [
        'label' => 'Store',
        'icon' => 'store',
        'items' => [
            ['route' => 'vendor.shop.settings', 'label' => 'Profil Toko', 'icon' => 'store', 'match' => 'vendor.shop.*'],
            ['route' => 'vendor.products.index', 'label' => 'Produk', 'icon' => 'package', 'match' => 'vendor.products.*'],
            ['route' => 'vendor.inventory.index', 'label' => 'Inventori', 'icon' => 'layers', 'match' => 'vendor.inventory.*'],
            ['route' => 'vendor.limited-stock.index', 'label' => 'Stok Menipis', 'icon' => 'alert-triangle', 'match' => 'vendor.limited-stock.*'],
            ['route' => 'vendor.reviews.index', 'label' => 'Ulasan', 'icon' => 'star', 'match' => 'vendor.reviews.*'],
        ],
    ],

    [
        'label' => 'Orders',
        'icon' => 'shopping-bag',
        'items' => [
            ['route' => 'vendor.orders.index', 'label' => 'Pesanan', 'icon' => 'list', 'match' => 'vendor.orders.*'],
            ['route' => 'vendor.fulfillment.index', 'label' => 'Fulfillment', 'icon' => 'package', 'match' => 'vendor.fulfillment.*'],
            ['route' => 'vendor.returns.index', 'label' => 'Retur', 'icon' => 'rotate-ccw', 'match' => 'vendor.returns.*'],
            ['route' => 'vendor.restock.index', 'label' => 'Permintaan Restock', 'icon' => 'bell', 'match' => 'vendor.restock.*'],
        ],
    ],

    [
        'label' => 'Customers',
        'icon' => 'users',
        'items' => [
            ['route' => 'vendor.customers.index', 'label' => 'Pelanggan', 'icon' => 'users', 'match' => 'vendor.customers.*'],
            ['route' => 'vendor.chat.inbox', 'label' => 'Percakapan', 'icon' => 'message-circle', 'match' => 'vendor.chat.*'],
        ],
    ],

    [
        'label' => 'Marketing',
        'icon' => 'megaphone',
        'items' => [
            ['route' => 'vendor.coupon.index', 'label' => 'Kupon', 'icon' => 'ticket', 'match' => 'vendor.coupon.*'],
            ['route' => 'vendor.promotions.index', 'label' => 'Promo', 'icon' => 'percent', 'match' => 'vendor.promotions.*'],
            ['route' => 'vendor.clearance.index', 'label' => 'Clearance', 'icon' => 'tag', 'match' => 'vendor.clearance.*'],
        ],
    ],

    [
        'label' => 'Finance',
        'icon' => 'cash-coin',
        'items' => [
            ['route' => 'vendor.finance.revenue', 'label' => 'Pendapatan', 'icon' => 'trending-up', 'match' => 'vendor.finance.revenue'],
            ['route' => 'vendor.finance.commission', 'label' => 'Komisi', 'icon' => 'percent', 'match' => 'vendor.finance.commission'],
            ['route' => 'vendor.finance.payouts', 'label' => 'Payout', 'icon' => 'download', 'match' => 'vendor.finance.payouts'],
            ['route' => 'vendor.wallet.index', 'label' => 'Dompet', 'icon' => 'wallet', 'match' => 'vendor.wallet.*'],
        ],
    ],

    [
        'label' => 'Analytics',
        'icon' => 'bar-chart',
        'items' => [
            ['route' => 'vendor.analytics.index', 'label' => 'Ringkasan', 'icon' => 'dashboard', 'match' => 'vendor.analytics.index'],
            ['route' => 'vendor.analytics.sales', 'label' => 'Penjualan', 'icon' => 'shopping-cart', 'match' => 'vendor.analytics.sales'],
            ['route' => 'vendor.analytics.products', 'label' => 'Produk', 'icon' => 'package', 'match' => 'vendor.analytics.products'],
            ['route' => 'vendor.analytics.customers', 'label' => 'Pelanggan', 'icon' => 'users', 'match' => 'vendor.analytics.customers'],
        ],
    ],

    [
        'label' => 'AI',
        'icon' => 'sparkles',
        'items' => [
            ['route' => 'vendor.ai.index', 'label' => 'Assistant', 'icon' => 'bot', 'match' => 'vendor.ai.index'],
        ],
    ],

    [
        'label' => 'POS',
        'icon' => 'scan-line',
        'items' => [
            ['route' => 'vendor.pos.index', 'label' => 'Kasir', 'icon' => 'monitor', 'match' => 'vendor.pos.*'],
            ['route' => 'vendor.barcode.index', 'label' => 'Barcode', 'icon' => 'barcode', 'match' => 'vendor.barcode.*'],
            ['route' => 'vendor.bulk-import.index', 'label' => 'Impor Massal', 'icon' => 'upload', 'match' => 'vendor.bulk-import.*'],
        ],
    ],

    [
        'label' => 'Support',
        'icon' => 'life-buoy',
        'items' => [
            ['route' => 'vendor.tickets.index', 'label' => 'Tiket', 'icon' => 'message-square', 'match' => 'vendor.tickets.*'],
            ['route' => 'vendor.help.index', 'label' => 'Bantuan', 'icon' => 'help-circle', 'match' => 'vendor.help.*'],
        ],
    ],

    [
        'label' => 'Settings',
        'icon' => 'settings',
        'items' => [
            ['route' => 'vendor.settings.index', 'label' => 'Toko', 'icon' => 'store', 'match' => 'vendor.settings.index'],
            ['route' => 'vendor.shipping.index', 'label' => 'Pengiriman', 'icon' => 'truck', 'match' => 'vendor.shipping.*'],
            ['route' => 'vendor.staff.index', 'label' => 'Staf', 'icon' => 'user-cog', 'match' => 'vendor.staff.*'],
            ['route' => 'vendor.notifications.index', 'label' => 'Notifikasi', 'icon' => 'bell', 'match' => 'vendor.notifications.*'],
            ['route' => 'vendor.security.index', 'label' => 'Keamanan', 'icon' => 'shield', 'match' => 'vendor.security.*'],
            ['route' => 'vendor.subscription.index', 'label' => 'Langganan', 'icon' => 'credit-card', 'match' => 'vendor.subscription.*'],
        ],
    ],
];
