<?php

declare(strict_types=1);

namespace App\Enums;

/** Platform module registry used for feature flags and the admin "Modules" screen. */
enum PlatformFeature: string
{
    case Storefront = 'storefront';
    case MultiVendor = 'multi_vendor';
    case Psoe = 'pseo';
    case Chat = 'chat';
    case Loyalty = 'loyalty';
    case Pos = 'pos';
    case Digital = 'digital';
    case GroupBuy = 'group_buy';
    case Bundle = 'bundle';
    case Ai = 'ai';
    case Wallet = 'wallet';
    case Affiliate = 'affiliate';
    case Api = 'api';
    case Webhooks = 'webhooks';
    case WhiteLabel = 'white_label';
    case Installer = 'installer';

    public function label(): string
    {
        return match ($this) {
            self::Storefront => 'Storefront',
            self::MultiVendor => 'Multi Vendor',
            self::Psoe => 'Programmatic SEO',
            self::Chat => 'Live Chat',
            self::Loyalty => 'Loyalty Points',
            self::Pos => 'Point of Sale',
            self::Digital => 'Produk Digital',
            self::GroupBuy => 'Group Buy',
            self::Bundle => 'Bundling',
            self::Ai => 'AI Copilot',
            self::Wallet => 'Dompet Digital',
            self::Affiliate => 'Affiliate',
            self::Api => 'Public API',
            self::Webhooks => 'Webhooks',
            self::WhiteLabel => 'White Label',
            self::Installer => 'Installer',
        };
    }

    /** Baseline availability. Anything in this list can be switched off per tenant. */
    public function isCore(): bool
    {
        return in_array($this, [self::Storefront, self::MultiVendor, self::Api], true);
    }

    /** @return list<self> */
    public static function coreOnly(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->isCore()));
    }
}
