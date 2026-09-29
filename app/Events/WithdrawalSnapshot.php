<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\VendorWithdrawRequest;

final class WithdrawalSnapshot
{
    /** @return array<string, mixed> */
    public static function summary(VendorWithdrawRequest $withdraw, bool $includeActor): array
    {
        $payload = [
            'id' => (int) $withdraw->getKey(),
            'vendor_id' => $withdraw->vendor_id,
            'shop_id' => $withdraw->shop_id,
            'amount' => (string) $withdraw->amount,
            'status' => (string) $withdraw->status,
            'note' => (string) $withdraw->note,
            'requested_at' => $withdraw->created_at?->toIso8601String(),
            'approved_at' => $withdraw->approved_at?->toIso8601String(),
            'completed_at' => $withdraw->completed_at?->toIso8601String(),
        ];

        if ($includeActor) {
            $payload['approved_by'] = $withdraw->approved_by;
            $payload['rejection_reason'] = $withdraw->rejection_reason;
        }

        return $payload;
    }
}
