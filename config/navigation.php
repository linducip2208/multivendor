<?php

declare(strict_types=1);

use App\Support\Currency;

/*
|--------------------------------------------------------------------------
| Admin information architecture
|--------------------------------------------------------------------------
|
| The backoffice navigation is data, not markup. Each entry declares the
| permission required to see it, so a role without Finance never sees the
| Finance group at all. The layout iterates this list; adding a module is a
| one-line change plus a controller.
|
*/

return [

    [
        'label' => 'Dashboard',
        'icon' => 'home',
        'items' => [
            ['route' => 'admin.dashboard', 'label' => 'Ringkasan', 'icon' => 'dashboard', 'permission' => null, 'match' => 'admin.dashboard'],
        ],
    ],

    [
        'label' => 'Commerce',
        'icon' => 'shopping-cart',
        'items' => [
            ['route' => 'admin.orders.index', 'label' => 'Pesanan', 'icon' => 'shopping-bag', 'permission' => 'orders.view', 'match' => 'admin.orders.*'],
            ['route' => 'admin.products.index', 'label' => 'Produk', 'icon' => 'package', 'permission' => 'products.view', 'match' => 'admin.products.*'],
            ['route' => 'admin.categories.index', 'label' => 'Kategori', 'icon' => 'category', 'permission' => 'categories.view', 'match' => 'admin.categories.*'],
            ['route' => 'admin.brands.index', 'label' => 'Brand', 'icon' => 'award', 'permission' => 'brands.view', 'match' => 'admin.brands.*'],
            ['route' => 'admin.inventory.index', 'label' => 'Inventori', 'icon' => 'layers', 'permission' => 'inventory.view', 'match' => 'admin.inventory.*'],
            ['route' => 'admin.inventory.warehouses', 'label' => 'Gudang', 'icon' => 'building', 'permission' => 'inventory.view', 'match' => 'admin.inventory.warehouses*'],
            ['route' => 'admin.bundles.index', 'label' => 'Bundling', 'icon' => 'box', 'permission' => 'products.view', 'match' => 'admin.bundles.*'],
            ['route' => 'admin.reviews.index', 'label' => 'Ulasan', 'icon' => 'star', 'permission' => 'reviews.view', 'match' => 'admin.reviews.*'],
        ],
    ],

    [
        'label' => 'Sellers',
        'icon' => 'store',
        'items' => [
            ['route' => 'admin.vendors.index', 'label' => 'Vendor', 'icon' => 'users', 'permission' => 'vendors.view', 'match' => 'admin.vendors.*'],
            ['route' => 'admin.vendors.applications', 'label' => 'Pendaftaran', 'icon' => 'user-plus', 'permission' => 'vendors.view', 'match' => 'admin.vendors.applications*'],
            ['route' => 'admin.commissions.index', 'label' => 'Komisi', 'icon' => 'percent', 'permission' => 'finance.view', 'match' => 'admin.commissions.*'],
            ['route' => 'admin.settlements.index', 'label' => 'Settlement', 'icon' => 'cash-coin', 'permission' => 'finance.view', 'match' => 'admin.settlements.*'],
            ['route' => 'admin.analytics.vendors', 'label' => 'Analitik Vendor', 'icon' => 'bar-chart', 'permission' => 'analytics.view', 'match' => 'admin.analytics.vendors'],
        ],
    ],

    [
        'label' => 'Customers',
        'icon' => 'users',
        'items' => [
            ['route' => 'admin.customers.index', 'label' => 'Pelanggan', 'icon' => 'user', 'permission' => 'customers.view', 'match' => 'admin.customers.*'],
            ['route' => 'admin.segments.index', 'label' => 'Segmen', 'icon' => 'layers', 'permission' => 'customers.view', 'match' => 'admin.segments.*'],
            ['route' => 'admin.loyalty.index', 'label' => 'Loyalty', 'icon' => 'award', 'permission' => 'customers.view', 'match' => 'admin.loyalty.*'],
            ['route' => 'admin.customers.wallets', 'label' => 'Dompet', 'icon' => 'wallet', 'permission' => 'finance.view', 'match' => 'admin.customers.wallet*'],
            ['route' => 'admin.wishlists.index', 'label' => 'Wishlist', 'icon' => 'heart', 'permission' => 'customers.view', 'match' => 'admin.wishlists.*'],
            ['route' => 'admin.activity-log', 'label' => 'Aktivitas', 'icon' => 'activity', 'permission' => 'customers.view', 'match' => 'admin.activity-log'],
        ],
    ],

    [
        'label' => 'Marketing',
        'icon' => 'megaphone',
        'items' => [
            ['route' => 'admin.campaigns.index', 'label' => 'Kampanye', 'icon' => 'target', 'permission' => 'marketing.view', 'match' => 'admin.campaigns.*'],
            ['route' => 'admin.coupons.index', 'label' => 'Kupon', 'icon' => 'ticket', 'permission' => 'marketing.view', 'match' => 'admin.coupons.*'],
            ['route' => 'admin.homepage.index', 'label' => 'Homepage', 'icon' => 'home', 'permission' => 'marketing.view', 'match' => 'admin.homepage.*'],
            ['route' => 'admin.flashdeals.index', 'label' => 'Flash Sale', 'icon' => 'zap', 'permission' => 'marketing.view', 'match' => 'admin.flashdeals.*'],
            ['route' => 'admin.deals.index', 'label' => 'Deal of the Day', 'icon' => 'clock', 'permission' => 'marketing.view', 'match' => 'admin.deals.*'],
            ['route' => 'admin.banners.index', 'label' => 'Banner', 'icon' => 'image', 'permission' => 'marketing.view', 'match' => 'admin.banners.*'],
            ['route' => 'admin.affiliates.index', 'label' => 'Affiliate', 'icon' => 'link', 'permission' => 'marketing.view', 'match' => 'admin.affiliates.*'],
            ['route' => 'admin.abandoned-carts', 'label' => 'Keranjang Tertinggal', 'icon' => 'shopping-cart', 'permission' => 'marketing.view', 'match' => 'admin.abandoned-carts*'],
            ['route' => 'admin.notifications', 'label' => 'Notifikasi', 'icon' => 'bell', 'permission' => 'marketing.view', 'match' => 'admin.notifications'],
        ],
    ],

    [
        'label' => 'Fulfillment',
        'icon' => 'truck',
        'items' => [
            ['route' => 'admin.shipments.index', 'label' => 'Pengiriman', 'icon' => 'truck', 'permission' => 'shipping.view', 'match' => 'admin.shipments.*'],
            ['route' => 'admin.couriers.index', 'label' => 'Kurir', 'icon' => 'globe', 'permission' => 'shipping.view', 'match' => 'admin.couriers.*'],
            ['route' => 'admin.delivery-men.index', 'label' => 'Kurir Internal', 'icon' => 'user-check', 'permission' => 'shipping.view', 'match' => 'admin.delivery-men*'],
            ['route' => 'admin.returns.index', 'label' => 'Retur', 'icon' => 'rotate-ccw', 'permission' => 'orders.view', 'match' => 'admin.returns.*'],
            ['route' => 'admin.shipping-category.index', 'label' => 'Biaya Kategori', 'icon' => 'settings', 'permission' => 'shipping.view', 'match' => 'admin.shipping-category.*'],
        ],
    ],

    [
        'label' => 'Finance',
        'icon' => 'cash-coin',
        'items' => [
            ['route' => 'admin.transactions.index', 'label' => 'Transaksi', 'icon' => 'receipt', 'permission' => 'finance.view', 'match' => 'admin.transactions.*'],
            ['route' => 'admin.payments.reconciliation', 'label' => 'Rekonsiliasi', 'icon' => 'refresh', 'permission' => 'finance.view', 'match' => 'admin.payments.*'],
            ['route' => 'admin.ledger.index', 'label' => 'Buku Besar', 'icon' => 'book', 'permission' => 'finance.view', 'match' => 'admin.ledger.*'],
            ['route' => 'admin.refunds.index', 'label' => 'Refund', 'icon' => 'undo', 'permission' => 'finance.view', 'match' => 'admin.refunds.*'],
            ['route' => 'admin.withdraws.index', 'label' => 'Payout', 'icon' => 'download', 'permission' => 'finance.view', 'match' => 'admin.withdraws.*'],
            ['route' => 'admin.vat.index', 'label' => 'Pajak', 'icon' => 'percent', 'permission' => 'finance.view', 'match' => 'admin.vat*'],
            ['route' => 'admin.tax-report.index', 'label' => 'Laporan Pajak', 'icon' => 'file-text', 'match' => 'admin.tax-report.*', 'permission' => 'finance.view'],
            ['route' => 'admin.offline-payment.index', 'label' => 'Pembayaran Offline', 'icon' => 'credit-card', 'permission' => 'finance.view', 'match' => 'admin.offline-payment.*'],
        ],
    ],

    [
        'label' => 'CRM',
        'icon' => 'message-square',
        'items' => [
            ['route' => 'admin.conversations.index', 'label' => 'Percakapan', 'icon' => 'message-circle', 'permission' => 'crm.view', 'match' => 'admin.conversations.*'],
            ['route' => 'admin.support-tickets.index', 'label' => 'Tiket', 'icon' => 'life-buoy', 'permission' => 'crm.view', 'match' => 'admin.support-tickets.*'],
        ],
    ],

    [
        'label' => 'AI',
        'icon' => 'sparkles',
        'items' => [
            ['route' => 'admin.ai.index', 'label' => 'Copilot', 'icon' => 'bot', 'permission' => 'ai.view', 'match' => 'admin.ai.*'],
            ['route' => 'admin.ai.prompts', 'label' => 'Prompt Templates', 'icon' => 'file-code', 'permission' => 'ai.view', 'match' => 'admin.ai.prompts*'],
            ['route' => 'admin.ai.usage', 'label' => 'Pemakaian', 'icon' => 'bar-chart', 'permission' => 'ai.view', 'match' => 'admin.ai.usage'],
        ],
    ],

    [
        'label' => 'Analytics',
        'icon' => 'bar-chart',
        'items' => [
            ['route' => 'admin.analytics.index', 'label' => 'Eksekutif', 'icon' => 'trending-up', 'permission' => 'analytics.view', 'match' => 'admin.analytics.index'],
            ['route' => 'admin.analytics.sales', 'label' => 'Penjualan', 'icon' => 'shopping-cart', 'permission' => 'analytics.view', 'match' => 'admin.analytics.sales'],
            ['route' => 'admin.analytics.customers', 'label' => 'Pelanggan', 'icon' => 'users', 'permission' => 'analytics.view', 'match' => 'admin.analytics.customers'],
            ['route' => 'admin.analytics.products', 'label' => 'Produk', 'icon' => 'package', 'permission' => 'analytics.view', 'match' => 'admin.analytics.products'],
            ['route' => 'admin.analytics.marketing', 'label' => 'Marketing', 'icon' => 'megaphone', 'permission' => 'analytics.view', 'match' => 'admin.analytics.marketing'],
            ['route' => 'admin.analytics.finance', 'label' => 'Keuangan', 'icon' => 'cash', 'permission' => 'finance.view', 'match' => 'admin.analytics.finance'],
            ['route' => 'admin.reports.index', 'label' => 'Laporan AI', 'icon' => 'file-bar', 'permission' => 'analytics.view', 'match' => 'admin.reports.*'],
            ['route' => 'admin.stock-report.index', 'label' => 'Laporan Stok', 'icon' => 'box', 'permission' => 'analytics.view', 'match' => 'admin.stock-report.*'],
            ['route' => 'admin.vendor-sale-report.index', 'label' => 'Penjualan Vendor', 'icon' => 'store', 'permission' => 'analytics.view', 'match' => 'admin.vendor-sale-report.*'],
        ],
    ],

    [
        'label' => 'Content',
        'icon' => 'file-text',
        'items' => [
            ['route' => 'admin.pages.index', 'label' => 'Halaman', 'icon' => 'file', 'permission' => 'content.view', 'match' => 'admin.pages.*'],
            ['route' => 'admin.blog.index', 'label' => 'Blog', 'icon' => 'edit-3', 'permission' => 'content.view', 'match' => 'admin.blog.*'],
            ['route' => 'admin.media.index', 'label' => 'Media', 'icon' => 'image', 'permission' => 'content.view', 'match' => 'admin.(media|file-manager).*'],
            ['route' => 'admin.menus.index', 'label' => 'Menu', 'icon' => 'list', 'permission' => 'content.view', 'match' => 'admin.menus.*'],
            ['route' => 'admin.seo.index', 'label' => 'SEO', 'icon' => 'search', 'permission' => 'seo.view', 'match' => 'admin.seo.index'],
            ['route' => 'admin.pseo.index', 'label' => 'PSEO', 'icon' => 'layers', 'permission' => 'seo.view', 'match' => 'admin.pseo.*'],
            ['route' => 'admin.seo.redirects', 'label' => 'Redirects', 'icon' => 'corner-up-right', 'permission' => 'seo.view', 'match' => 'admin.seo.redirects*'],
            ['route' => 'admin.product-seo.index', 'label' => 'Product SEO', 'icon' => 'search', 'permission' => 'seo.view', 'match' => 'admin.product-seo.*'],
        ],
    ],

    [
        'label' => 'Developers',
        'icon' => 'code',
        'items' => [
            ['route' => 'admin.api.index', 'label' => 'API', 'icon' => 'server', 'permission' => 'developers.view', 'match' => 'admin.api.*'],
            ['route' => 'admin.api-keys.index', 'label' => 'API Keys', 'icon' => 'key', 'permission' => 'developers.view', 'match' => 'admin.api-keys.*'],
            ['route' => 'admin.webhooks.index', 'label' => 'Webhooks', 'icon' => 'webhook', 'permission' => 'developers.view', 'match' => 'admin.webhooks.*'],
            ['route' => 'admin.events.index', 'label' => 'Events', 'icon' => 'zap', 'permission' => 'developers.view', 'match' => 'admin.events.*'],
            ['route' => 'admin.logs.index', 'label' => 'Logs', 'icon' => 'file-code', 'permission' => 'developers.view', 'match' => 'admin.logs.*'],
        ],
    ],

    [
        'label' => 'SaaS',
        'icon' => 'layers',
        'items' => [
            ['route' => 'admin.tenants.index', 'label' => 'Tenants', 'icon' => 'globe', 'permission' => 'saas.view', 'match' => 'admin.tenants.*'],
            ['route' => 'admin.saas.plans', 'label' => 'Paket', 'icon' => 'credit-card', 'permission' => 'saas.view', 'match' => 'admin.saas.plans*'],
            ['route' => 'admin.saas.subscriptions', 'label' => 'Langganan', 'icon' => 'refresh', 'permission' => 'saas.view', 'match' => 'admin.saas.subscriptions*'],
            ['route' => 'admin.saas.usage', 'label' => 'Pemakaian', 'icon' => 'gauge', 'permission' => 'saas.view', 'match' => 'admin.saas.usage'],
            ['route' => 'admin.modules.index', 'label' => 'Fitur', 'icon' => 'toggle-left', 'permission' => 'saas.view', 'match' => 'admin.modules.*'],
        ],
    ],

    [
        'label' => 'System',
        'icon' => 'settings',
        'items' => [
            ['route' => 'admin.settings', 'label' => 'Pengaturan', 'icon' => 'sliders', 'permission' => 'settings.view', 'match' => 'admin.settings'],
            ['route' => 'admin.users.index', 'label' => 'Pengguna', 'icon' => 'user-cog', 'permission' => 'users.view', 'match' => 'admin.users.*'],
            ['route' => 'admin.roles.index', 'label' => 'Peran', 'icon' => 'shield', 'permission' => 'users.view', 'match' => 'admin.roles.*'],
            ['route' => 'admin.permissions.index', 'label' => 'Izin', 'icon' => 'key-square', 'permission' => 'users.view', 'match' => 'admin.permissions.*'],
            ['route' => 'admin.providers.index', 'label' => 'Provider', 'icon' => 'plug', 'permission' => 'settings.view', 'match' => 'admin.providers.*'],
            ['route' => 'admin.theme.index', 'label' => 'Tema', 'icon' => 'palette', 'permission' => 'settings.view', 'match' => 'admin.theme.*'],
            ['route' => 'admin.email-templates.index', 'label' => 'Email', 'icon' => 'mail', 'permission' => 'settings.view', 'match' => 'admin.email-templates.*'],
            ['route' => 'admin.system.health', 'label' => 'Kesehatan Sistem', 'icon' => 'activity', 'permission' => 'system.view', 'match' => 'admin.system.health'],
            ['route' => 'admin.system.logs', 'label' => 'Error Log', 'icon' => 'alert-triangle', 'permission' => 'system.view', 'match' => 'admin.system.logs'],
            ['route' => 'admin.system.queue', 'label' => 'Queue', 'icon' => 'list-ordered', 'permission' => 'system.view', 'match' => 'admin.system.queue'],
            ['route' => 'admin.audit-logs', 'label' => 'Audit Trail', 'icon' => 'history', 'permission' => 'system.view', 'match' => 'admin.audit-logs'],
        ],
    ],
];
