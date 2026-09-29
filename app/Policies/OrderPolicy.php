<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Vendor/customer-scoped order authorization.
 *
 * NOTE: registered for future use only — not enforced in controllers yet.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor() || $user->isCustomer();
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isVendor() && $this->ownsShopRecord($user, (int) $order->shop_id)) {
            return true;
        }

        return $user->isCustomer() && (int) $order->customer_id === (int) $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isVendor() && $this->ownsShopRecord($user, (int) $order->shop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isVendor() || $user->isCustomer();
    }

    public function delete(User $user, Order $order): bool
    {
        // Orders are financial records: only backoffice may hard-delete.
        // Vendors/customers use cancel/refund flows instead.
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    private function ownsShopRecord(User $user, int $shopId): bool
    {
        if ($shopId <= 0) {
            return false;
        }

        $ownShopId = (int) ($user->shop?->id ?? 0);

        return $ownShopId > 0 && $ownShopId === $shopId;
    }
}
