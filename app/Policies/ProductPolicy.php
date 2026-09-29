<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Vendor-scoped product authorization.
 *
 * NOTE: registered for future use only — not enforced in controllers yet.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor();
    }

    public function view(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownsShopRecord($user, (int) $product->shop_id);
    }

    public function update(User $user, Product $product): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownsShopRecord($user, (int) $product->shop_id);
    }

    private function ownsShopRecord(User $user, int $shopId): bool
    {
        if (! $user->isVendor() || $shopId <= 0) {
            return false;
        }

        $ownShopId = (int) ($user->shop?->id ?? 0);

        return $ownShopId > 0 && $ownShopId === $shopId;
    }
}
